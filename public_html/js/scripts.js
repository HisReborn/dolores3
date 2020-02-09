// Returns the length in bytes of a given string
function getByteLen(string) {
  var bytes = 0;

  for(var i = 0; i < string.length; i++){
    var c = string.charCodeAt(i);

    bytes += c < (1 <<  7) ? 1 :
             c < (1 << 11) ? 2 :
             c < (1 << 16) ? 3 :
             c < (1 << 21) ? 4 :
             c < (1 << 26) ? 5 :
             c < (1 << 31) ? 6 : Number.NaN;
  }

  return bytes;
}

function htmlEncode(html){

    return document.createElement('a').appendChild(
        document.createTextNode(html) ).parentNode.innerHTML;
}

function htmlDecode(html){
    var a = document.createElement('a'); a.innerHTML = html;

    return a.textContent;
}
