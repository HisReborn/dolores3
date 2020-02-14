
<?php

/*
 * OP_RETURN.php
 *
 * PHP script to generate and retrieve OP_RETURN bitcoin transactions
 *
 * Copyright (c) Coin Sciences Ltd
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

	define('OP_RETURN_BITCOIN_IP', '127.0.0.1'); // IP address of your bitcoin node
	define('OP_RETURN_BITCOIN_USE_CMD', false); // use command-line instead of JSON-RPC?

	if (OP_RETURN_BITCOIN_USE_CMD) {
		define('OP_RETURN_BITCOIN_PATH', '/usr/bin/bitcoin-cli'); // path to bitcoin-cli executable on this server

	} else {
		define('OP_RETURN_BITCOIN_PORT', 'PORT'); // leave empty to use default port for mainnet/testnet
		define('OP_RETURN_BITCOIN_USER', 'YOUR_USERNAME'); // leave empty to read from ~/.bitcoin/bitcoin.conf (Unix only)
		define('OP_RETURN_BITCOIN_PASSWORD', 'YOUR_PASSWORD'); // leave empty to read from ~/.bitcoin/bitcoin.conf (Unix only)
	}

	define('OP_RETURN_BTC_FEE', 0.0001); // BTC fee to pay per transaction
	define('OP_RETURN_BTC_DUST', 0.00001); // omit BTC outputs smaller than this

	define('OP_RETURN_MAX_BYTES', 80); // maximum bytes in an OP_RETURN (40 as of Bitcoin 0.10, 80 v0.11+)
	define('OP_RETURN_MAX_BLOCKS', 10); // maximum number of blocks to try when retrieving data

	define('OP_RETURN_NET_TIMEOUT_CONNECT', 5); // how long to time out when connecting to bitcoin node
	define('OP_RETURN_NET_TIMEOUT_RECEIVE', 10); // how long to time out retrieving data from bitcoin node


//	User-facing functions

	function OP_RETURN_send($send_address, $send_amount, $metadata, $testnet=false)
	{

	//	Validate some parameters

		if (!OP_RETURN_bitcoin_check($testnet))
			return array('error' => 'Please check Bitcoin Core is running and OP_RETURN_BITCOIN_* constants are set correctly');

		$result=OP_RETURN_bitcoin_cmd('validateaddress', $testnet, $send_address);
		if (!$result['isvalid'])
			return array('error' => 'Send address could not be validated: '.$send_address);

		$metadata_len=strlen($metadata);

		if ($metadata_len>65536)
			return array('error' => 'This library only supports metadata up to 65536 bytes in size');

		if ($metadata_len>OP_RETURN_MAX_BYTES)
			return array('error' => 'Metadata has '.$metadata_len.' bytes but is limited to '.OP_RETURN_MAX_BYTES.' (see OP_RETURN_MAX_BYTES)');


	//	Calculate amounts and choose inputs

		$output_amount=$send_amount+OP_RETURN_BTC_FEE;

		$inputs_spend=OP_RETURN_select_inputs($output_amount, $testnet);

		if (isset($inputs_spend['error']))
			return $inputs_spend;

		$change_amount=$inputs_spend['total']-$output_amount;


	//	Build the raw transaction

		$change_address=OP_RETURN_bitcoin_cmd('getrawchangeaddress', $testnet);

		$outputs=array($send_address => (float)$send_amount);

		if ($change_amount>=OP_RETURN_BTC_DUST)
			$outputs[$change_address]=$change_amount;

		$raw_txn=OP_RETURN_create_txn($inputs_spend['inputs'], $outputs, $metadata, count($outputs), $testnet);


	//	Sign and send the transaction, return result

		return OP_RETURN_sign_send_txn($raw_txn, $testnet);
	}


	function OP_RETURN_store($data, $testnet=false)
	{
	/*
		Data is stored in OP_RETURNs within a series of chained transactions.
		The data is referred to by the txid of the first transaction containing an OP_RETURN.
		If the OP_RETURN is followed by another output, the data continues in the transaction spending that output.
		When the OP_RETURN is the last output, this also signifies the end of the data.
	*/

	//	Validate parameters and get change address

		if (!OP_RETURN_bitcoin_check($testnet))
			return array('error' => 'Please check Bitcoin Core is running and OP_RETURN_BITCOIN_* constants are set correctly');

		$data_len=strlen($data);
		if ($data_len==0)
			return array('error' => 'Some data is required to be stored');

		$change_address=OP_RETURN_bitcoin_cmd('getrawchangeaddress', $testnet);


	//	Calculate amounts and choose first inputs to use

		$output_amount=OP_RETURN_BTC_FEE*ceil($data_len/OP_RETURN_MAX_BYTES); // number of transactions required

		$inputs_spend=OP_RETURN_select_inputs($output_amount, $testnet);
		if (isset($inputs_spend['error']))
			return $inputs_spend;

		$inputs=$inputs_spend['inputs'];
		$input_amount=$inputs_spend['total'];


	//	Find the current blockchain height and mempool txids

		$height=OP_RETURN_bitcoin_cmd('getblockcount', $testnet);
		$avoid_txids=OP_RETURN_bitcoin_cmd('getrawmempool', $testnet);


	//	Loop to build and send transactions

		$result['txids']=array();

		for ($data_ptr=0; $data_ptr<$data_len; $data_ptr+=OP_RETURN_MAX_BYTES) {

		//	Some preparation for this iteration

			$last_txn=(($data_ptr+OP_RETURN_MAX_BYTES)>=$data_len); // is this the last tx in the chain?
			$change_amount=$input_amount-OP_RETURN_BTC_FEE;
			$metadata=substr($data, $data_ptr, OP_RETURN_MAX_BYTES);

		//	Build and send this transaction

			$outputs=array();
			if ($change_amount>=OP_RETURN_BTC_DUST) // might be skipped for last transaction
				$outputs[$change_address]=$change_amount;

			$raw_txn=OP_RETURN_create_txn($inputs, $outputs, $metadata, $last_txn ? count($outputs) : 0, $testnet);

			$send_result=OP_RETURN_sign_send_txn($raw_txn, $testnet);

		//	Check for errors and collect the txid

			if (isset($send_result['error'])) {
				$result['error']=$send_result['error'];
				break;
			}

			$result['txids'][]=$send_result['txid'];

			if ($data_ptr==0)
				$result['ref']=OP_RETURN_calc_ref($height, $send_result['txid'], $avoid_txids);

		//	Prepare inputs for next iteration

			$inputs=array(array(
				'txid' => $send_result['txid'],
				'vout' => 1,
			));

			$input_amount=$change_amount;
		}


	//	Return the final result

		return $result;
	}


	function OP_RETURN_retrieve($ref, $max_results=1, $testnet=false)
	{

	//	Validate parameters and get status of Bitcoin Core

		if (!OP_RETURN_bitcoin_check($testnet))
			return array('error' => 'Please check Bitcoin Core is running and OP_RETURN_BITCOIN_* constants are set correctly');

		$max_height=OP_RETURN_bitcoin_cmd('getblockcount', $testnet);
		$heights=OP_RETURN_get_ref_heights($ref, $max_height);

		if (!is_array($heights))
			return array('error' => 'Ref is not valid');


	//	Collect and return the results

		$results=array();

		foreach ($heights as $height) {
			if ($height==0) {
				$txids=OP_RETURN_list_mempool_txns($testnet); // if mempool, only get list for now (to save RPC calls)
				$txns=null;
			} else {
				$txns=OP_RETURN_get_block_txns($height, $testnet); // if block, get all fully unpacked
				$txids=array_keys($txns);
			}

			foreach ($txids as $txid)
				if (OP_RETURN_match_ref_txid($ref, $txid)) {
					if ($height==0)
						$txn_unpacked=OP_RETURN_get_mempool_txn($txid, $testnet);
					else
						$txn_unpacked=$txns[$txid];

					$found=OP_RETURN_find_txn_data($txn_unpacked);

					if (is_array($found)) {

					//	Collect data from txid which matches $ref and contains an OP_RETURN

						$result=array(
							'txids' => array($txid),
							'data' => $found['op_return'],
						);

					//	Work out which other block heights / mempool we should try

						$key_heights=array($height => true);

						if ($height==0)
							$try_heights=array(); // nowhere else to look if first still in mempool
						else {
							$result['ref']=OP_RETURN_calc_ref($height, $txid, array_keys($txns));
							$try_heights=OP_RETURN_get_try_heights($height+1, $max_height, false);
						}

					//	Collect the rest of the data, if appropriate

						if ($height==0)
							$this_txns=OP_RETURN_get_mempool_txns($testnet); // now retrieve all to follow chain
						else
							$this_txns=$txns;

						$last_txid=$txid;
						$this_height=$height;

						while ($found['index'] < (count($txn_unpacked['vout'])-1)) { // this means more data to come
							$next_txid=OP_RETURN_find_spent_txid($this_txns, $last_txid, $found['index']+1);

						//	If we found the next txid in the data chain

							if (isset($next_txid)) {
								$result['txids'][]=$next_txid;

								$txn_unpacked=$this_txns[$next_txid];
								$found=OP_RETURN_find_txn_data($txn_unpacked);

								if (is_array($found)) {
									$result['data'].=$found['op_return'];
									$key_heights[$this_height]=true;
								} else {
									$result['error']='Data incomplete - missing OP_RETURN';
									break;
								}

								$last_txid=$next_txid;

						//	Otherwise move on to the next height to keep looking

							} else {
								if (count($try_heights)) {
									$this_height=array_shift($try_heights);

									if ($this_height==0)
										$this_txns=OP_RETURN_get_mempool_txns($testnet);
									else
										$this_txns=OP_RETURN_get_block_txns($this_height, $testnet);

								} else {
									$result['error']='Data incomplete - could not find next transaction';
									break;
								}
							}
						}

					//	Finish up the information about this result

						$result['heights']=array_keys($key_heights);

						$results[]=$result;
					}
				}

			if (count($results)>=$max_results)
				break; // stop if we have collected enough
		}

		return $results;
	}


//	Utility functions

	function OP_RETURN_select_inputs($total_amount, $testnet)
	{

	//	List and sort unspent inputs by priority

		$unspent_inputs=OP_RETURN_bitcoin_cmd('listunspent', $testnet, 0);
		if (!is_array($unspent_inputs))
			return array('error' => 'Could not retrieve list of unspent inputs');

		foreach ($unspent_inputs as $index => $unspent_input)
			$unspent_inputs[$index]['priority']=$unspent_input['amount']*$unspent_input['confirmations'];
				// see: https://en.bitcoin.it/wiki/Transaction_fees

		OP_RETURN_sort_by($unspent_inputs, 'priority');
		$unspent_inputs=array_reverse($unspent_inputs); // now in descending order of priority

	//	Identify which inputs should be spent

		$inputs_spend=array();
		$input_amount=0;

		foreach ($unspent_inputs as $unspent_input) {
			$inputs_spend[]=$unspent_input;

			$input_amount+=$unspent_input['amount'];
			if ($input_amount>=$total_amount)
				break; // stop when we have enough
		}

		if ($input_amount<$total_amount)
			return array('error' => 'Not enough funds are available to cover the amount and fee');

	//	Return the successful result

		return array(
			'inputs' => $inputs_spend,
			'total' => $input_amount,
		);
	}

	function OP_RETURN_create_txn($inputs, $outputs, $metadata, $metadata_pos, $testnet)
	{
		$raw_txn=OP_RETURN_bitcoin_cmd('createrawtransaction', $testnet, $inputs, $outputs);

		$txn_unpacked=OP_RETURN_unpack_txn(pack('H*', $raw_txn));

		$metadata_len=strlen($metadata);

		if ($metadata_len<=75)
			$payload=chr($metadata_len).$metadata; // length byte + data (https://en.bitcoin.it/wiki/Script)
		elseif ($metadata_len<=256)
			$payload="\x4c".chr($metadata_len).$metadata; // OP_PUSHDATA1 format
		else
			$payload="\x4d".chr($metadata_len%256).chr(floor($metadata_len/256)).$metadata; // OP_PUSHDATA2 format

		$metadata_pos=min(max(0, $metadata_pos), count($txn_unpacked['vout'])); // constrain to valid values

		array_splice($txn_unpacked['vout'], $metadata_pos, 0, array(array(
			'value' => 0,
			'scriptPubKey' => '6a'.reset(unpack('H*', $payload)), // here's the OP_RETURN
		)));

		return reset(unpack('H*', OP_RETURN_pack_txn($txn_unpacked)));
	}

	function OP_RETURN_sign_send_txn($raw_txn, $testnet)
	{
		$signed_txn=OP_RETURN_bitcoin_cmd('signrawtransaction', $testnet, $raw_txn);
		if (!$signed_txn['complete'])
			return array('error' => 'Could not sign the transaction');

		$send_txid=OP_RETURN_bitcoin_cmd('sendrawtransaction', $testnet, $signed_txn['hex']);
		if (strlen($send_txid)!=64)
			return array('error' => 'Could not send the transaction');

		return array('txid' => $send_txid);
	}

	function OP_RETURN_get_height_txns($height, $testnet)
	{
		if ($height==0)
			return OP_RETURN_get_mempool_txns($testnet);
		else
			return OP_RETURN_get_block_txns($height, $testnet);
	}

	function OP_RETURN_list_mempool_txns($testnet)
	{
		return OP_RETURN_bitcoin_cmd('getrawmempool', $testnet);
	}

	function OP_RETURN_get_mempool_txn($txid, $testnet)
	{
		$raw_txn=OP_RETURN_bitcoin_cmd('getrawtransaction', $testnet, $txid);
		return OP_RETURN_unpack_txn(pack('H*', $raw_txn));
	}

	function OP_RETURN_get_mempool_txns($testnet)
	{
		$txids=OP_RETURN_list_mempool_txns($testnet);

		$txns=array();
		foreach ($txids as $txid)
			$txns[$txid]=OP_RETURN_get_mempool_txn($txid, $testnet);

		return $txns;
	}

	function OP_RETURN_get_raw_block($height, $testnet)
	{
		$block_hash=OP_RETURN_bitcoin_cmd('getblockhash', $testnet, $height);
		if (strlen($block_hash)!=64)
			return array('error' => 'Block at height '.$height.' not found');

		return array(
			'block' => pack('H*', OP_RETURN_bitcoin_cmd('getblock', $testnet, $block_hash, false))
		);
	}

	function OP_RETURN_get_block_txns($height, $testnet)
	{
		$raw_block=OP_RETURN_get_raw_block($height, $testnet);
		if (isset($raw_block['error']))
			return array('error' => $raw_block['error']);

		$block=OP_RETURN_unpack_block($raw_block['block']);

		return $block['txs'];
	}


//	Talking to bitcoin-cli

	function OP_RETURN_bitcoin_check($testnet)
	{
		$info=OP_RETURN_bitcoin_cmd('getinfo', $testnet);

		return is_array($info);
	}

	function OP_RETURN_bitcoin_cmd($command, $testnet) // more params are read from here
	{
		$args=func_get_args();
		array_shift($args);
		array_shift($args);

		if (OP_RETURN_BITCOIN_USE_CMD) {
			$command=OP_RETURN_BITCOIN_PATH.' '.($testnet ? '-testnet ' : '').escapeshellarg($command);

			foreach ($args as $arg)
				$command.=' '.escapeshellarg(is_array($arg) ? json_encode($arg) : $arg);

			$raw_result=rtrim(shell_exec($command), "\n");

			$result=json_decode($raw_result, true); // decode JSON if possible
			if (!isset($result))
				$result=$raw_result;

		} else {
			$request=array(
				'id' => time().'-'.rand(100000,999999),
				'method' => $command,
				'params' => $args,
			);

			$port=OP_RETURN_BITCOIN_PORT;
			$user=OP_RETURN_BITCOIN_USER;
			$password=OP_RETURN_BITCOIN_PASSWORD;

			if (
				function_exists('posix_getpwuid') &&
				!(strlen($port) && strlen($user) && strlen($password))
			) {
				$posix_userinfo=posix_getpwuid(posix_getuid());
				$bitcoin_conf=file_get_contents($posix_userinfo['dir'].'/.bitcoin/bitcoin.conf');
				$conf_lines=preg_split('/[\n\r]/', $bitcoin_conf);

				foreach ($conf_lines as $conf_line) {
					$parts=explode('=', trim($conf_line), 2);

					if ( ($parts[0]=='rpcport') && !strlen($port) )
						$port=$parts[1];
					if ( ($parts[0]=='rpcuser') && !strlen($user) )
						$user=$parts[1];
					if ( ($parts[0]=='rpcpassword') && !strlen($password) )
						$password=$parts[1];
				}
			}

			if (!strlen($port))
				$port=$testnet ? 18332 : 8332;

			if (!strlen($user) && strlen($password))
				return null; // no point trying in this case

			$curl=curl_init('http://'.OP_RETURN_BITCOIN_IP.':'.$port.'/');
			curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($curl, CURLOPT_USERPWD, $user.':'.$password);
			curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, OP_RETURN_NET_TIMEOUT_CONNECT);
			curl_setopt($curl, CURLOPT_TIMEOUT, OP_RETURN_NET_TIMEOUT_RECEIVE);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($request));
			$raw_result=curl_exec($curl);
			curl_close($curl);

			$result_array=json_decode($raw_result, true);
			$result=@$result_array['result'];
		}

		return $result;
	}


//	Working with data references

	/*
		The format of a data reference is: [estimated block height]-[partial txid] - where:

		[estimated block height] is the block where the first transaction might appear and following
		which all subsequent transactions are expected to appear. In the event of a weird blockchain
		reorg, it is possible the first transaction might appear in a slightly earlier block. When
		embedding data, we set [estimated block height] to 1+(the current block height).

		[partial txid] contains 2 adjacent bytes from the txid, at a specific position in the txid:
		2*([partial txid] div 65536) gives the offset of the 2 adjacent bytes, between 0 and 28.
		([partial txid] mod 256) is the byte of the txid at that offset.
		(([partial txid] mod 65536) div 256) is the byte of the txid at that offset plus one.
		Note that the txid is ordered according to user presentation, not raw data in the block.
	*/

	function OP_RETURN_calc_ref($next_height, $txid, $avoid_txids)
	{
		$txid_binary=pack('H*', $txid);

		for ($txid_offset=0; $txid_offset<=14; $txid_offset++) {
			$sub_txid=substr($txid_binary, 2*$txid_offset, 2);