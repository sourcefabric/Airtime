function showErrorSections() {

    $(".errors").each(function(i){
        if($(this).length > 0){
            var div = $(this).closest("div")
            if(div.attr('class') == "stream-setting-content"){
                $(this).closest("div").show();
                $(this).closest("fieldset").removeClass('closed');
                $(window).scrollTop($(this).closest("div").position().top);
            }
        }
    });
}
function rebuildStreamURL(ele){
    var div = ele.closest("div")
    host = div.find("input:[id$=-host]").val()
    port = div.find("input:[id$=-port]").val()
    mount = div.find("input:[id$=-mount]").val()
    streamurl = ""
    if(div.find("select:[id$=-output]").val()=="icecast"){
        streamurl = "http://"+host
        if($.trim(port) != ""){
            streamurl += ":"+port
        }
        if($.trim(mount) != ""){
            streamurl += "/"+mount
        }
    }else{
        streamurl = "http://"+host+":"+port+"/"
    }
    div.find("#stream_url").html(streamurl)
}
function restrictOggBitrate(ele, on){
    var div = ele.closest("div")
    if(on){
        if(parseInt(div.find("select[id$=data-bitrate]").val(),10) < 48){
            div.find("select[id$=data-bitrate]").find("option[value='48']").attr("selected","selected");
        }
        div.find("select[id$=data-bitrate]").find("option[value='24']").attr("disabled","disabled");
        div.find("select[id$=data-bitrate]").find("option[value='32']").attr("disabled","disabled");
    }else{
        div.find("select[id$=data-bitrate]").find("option[value='24']").attr("disabled","");
        div.find("select[id$=data-bitrate]").find("option[value='32']").attr("disabled","");
    }
}
function hideForShoutcast(ele){
    var div = ele.closest("div")
    div.find("#outputMountpoint-label").hide()
    div.find("#outputMountpoint-element").hide()
    div.find("#outputUser-label").hide()
    div.find("#outputUser-element").hide()
    div.find("select[id$=data-type]").find("option[value='mp3']").attr('selected','selected');
    div.find("select[id$=data-type]").find("option[value='ogg']").attr("disabled","disabled");
    
    restrictOggBitrate(ele, false)
}

function validate(ele,evt) {
    var theEvent = evt || window.event;
    var key = theEvent.keyCode || theEvent.which;
    if ((ele.val().length >= 5 || (key < 48 || key > 57)) && !(key == 8 || key == 9 || key == 13 || key == 37 || key == 39 || key == 46)) {
      theEvent.returnValue = false;
      if(theEvent.preventDefault) theEvent.preventDefault();
    }
  }


function showForIcecast(ele){
    var div = ele.closest("div")
    div.find("#outputMountpoint-label").show()
    div.find("#outputMountpoint-element").show()
    div.find("#outputUser-label").show()
    div.find("#outputUser-element").show()
    div.find("select[id$=data-type]").find("option[value='ogg']").attr("disabled","");
}

function checkLiquidsoapStatus(){
    var url = '/Preference/get-liquidsoap-status/format/json';
    var id = $(this).attr("id");
    $.post(url, function(json){
        var json_obj = jQuery.parseJSON(json);
        for(var i=0;i<json_obj.length;i++){
            var obj = json_obj[i];
            var id;
            var status;
            for(var key in obj){
                if(key == "id"){
                    id = obj[key];
                }
                if(key == "status"){
                    status = obj[key];
                }
            }
            var html;
            if(status == "OK"){
                html = '<div class="stream-status status-good"><h3>Connected to the streaming server</h3></div>';
            }else if(status == "N/A"){
                html = '<div class="stream-status status-disabled"><h3>The stream is disabled</h3></div>';
            }else if(status == "waiting"){
            	html = '<div class="stream-status status-info"><h3>Getting information from the server...</h3></div>';
            }else{
                html = '<div class="stream-status status-error"><h3>Can not connect to the streaming server</h3><p>'+status+'</p></div>';
            }
            $("#s"+id+"Liquidsoap-error-msg-element").html(html);
        }
    });
}


$(document).ready(function() {
    // initial stream url
    $("dd[id=outputStreamURL-element]").each(function(){
        rebuildStreamURL($(this))
    })
    
    $("input:[id$=-host], input:[id$=-port], input:[id$=-mount]").keyup(function(){
        rebuildStreamURL($(this))
    })
    
    $("input:[id$=-port]").keypress(function(e){
        validate($(this),e)
    })
    
    $("select:[id$=-output]").change(function(){
        rebuildStreamURL($(this))
    })
    
    $("#output_sound_device").change(function(){
        if($(this).is(':checked')){
        	$("select[id=output_sound_device_type]").removeAttr('disabled')
        }else{
        	$("select[id=output_sound_device_type]").attr('disabled', 'disabled')
        }
    })
    
    $("select[id$=data-type]").change(function(){
        if($(this).val() == 'ogg'){
            restrictOggBitrate($(this), true)
        }else{
            restrictOggBitrate($(this), false)
        }
    })
    
    $("select[id$=data-type]").each(function(){
        if($(this).val() == 'ogg'){
            restrictOggBitrate($(this), true)
        }
    })
    
    $("select[id$=data-output]").change(function(){
        if($(this).val() == 'shoutcast'){
            hideForShoutcast($(this))
        }else{
            showForIcecast($(this))
        }
    })
    
    $("select[id$=data-output]").each(function(){
        if($(this).val() == 'shoutcast'){
            hideForShoutcast($(this))
        }
    })
    
    $('.toggle legend').live('click',function() {
        $(this).parent().toggleClass('closed');
        return false;
    });
    
    $('.collapsible-header').click(function() {
        $(this).next().toggle('fast');
        $(this).toggleClass("close");
        return false;
    })
    
    showErrorSections()
    setInterval('checkLiquidsoapStatus()', 1000)
    
});
