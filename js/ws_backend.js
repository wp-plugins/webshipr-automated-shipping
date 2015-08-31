function process_order(){

    var e = document.getElementById("ws_rate");
   
    var strId = e.options[e.selectedIndex].value;
    var s = document.getElementById("swipbox");

    if(s !== null){
        var strS = s.options[s.selectedIndex].value;
    }
    var cur_url = document.URL.split("&webshipr_process=true")[0].split("&webshipr_reprocess=true")[0];

    if(s !== null){
        window.location = cur_url+"&webshipr_process=true&ws_rate="+strId+"&swipbox="+strS;
    }else{
        window.location = cur_url+"&webshipr_process=true&ws_rate="+strId;
    }
}

function reprocess_order(){
    var e = document.getElementById("ws_rate");
    var strId = e.options[e.selectedIndex].value;
    var cur_url = document.URL.split("&webshipr_process=true")[0].split("&webshipr_reprocess=true")[0];

    window.location = cur_url+"&webshipr_reprocess=true&ws_rate="+strId;
}