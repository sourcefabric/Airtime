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
    	div.find("select[id$=data-bitrate]").find("option[value='24']").removeAttr("disabled");
        div.find("select[id$=data-bitrate]").find("option[value='32']").removeAttr("disabled");
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

function setLiveSourceConnectionOverrideListener(){
    $("[id=connection_url_override]").click(function(event){
        var url_input = $(this).parent().find("#stream_url").children()
        url_input.removeAttr("readonly")
		
        $(this).parent().find("div[id$='_dj_connection_url_actions']").show()
        event.preventDefault()
    })
    
    // set action for "OK" and "X"
    var live_dj_actions = $("#live_dj_connection_url_actions")
    var live_dj_input = live_dj_actions.parent().find("#stream_url").children()
    var master_dj_actions = $("#master_dj_connection_url_actions")
    var master_dj_input = master_dj_actions.parent().find("#stream_url").children()
    
    live_dj_actions.find("#ok").click(function(event){
    	event.preventDefault()
        var url = live_dj_input.val()
        live_dj_input.val(url)
        live_dj_input.attr("readonly", "readonly")
        live_dj_actions.hide()
        $.get("/Preference/set-source-connection-url/", {format: "json", type: "livedj", url:encodeURIComponent(url), override: 1});
    	event.preventDefault()
    })
    
    live_dj_actions.find("#reset").click(function(event){
    	event.preventDefault()
        var port = $("#dj_harbor_input_port").val()
        var mount = $("#dj_harbor_input_mount_point").val()
        var url = "http://"+location.hostname+":"+port+"/"+mount
        if (port == '' || mount == '') {
            url = 'N/A'
        }
        live_dj_input.val(url)
        live_dj_input.attr("readonly", "readonly")
        live_dj_actions.hide()
        $.get("/Preference/set-source-connection-url", {format: "json", type: "livedj", url:encodeURIComponent(url), override: 0});
    	event.preventDefault()
    })
    
    master_dj_actions.find("#ok").click(function(event){
        var url = master_dj_input.val()
        master_dj_input.val(url)
        master_dj_input.attr("readonly", "readonly")
        master_dj_actions.hide()
        $.get("/Preference/set-source-connection-url", {format: "json", type: "masterdj", url:encodeURIComponent(url), override: 1})
        event.preventDefault()
    })
    
    master_dj_actions.find("#reset").click(function(event){
        var port = $("#master_harbor_input_port").val()
        var mount = $("#master_harbor_input_mount_point").val()
        var url = "http://"+location.hostname+":"+port+"/"+mount
        if (port == '' || mount == '') {
            url = 'N/A'
        }
        master_dj_input.val(url)
        master_dj_input.attr("readonly", "readonly")
        master_dj_actions.hide()
        $.get("/Preference/set-source-connection-url", {format: "json", type: "masterdj", url:encodeURIComponent(url), override: 0})
        event.preventDefault()
    })
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
    
    if(!$("#output_sound_device").is(':checked')){
        $("select[id=output_sound_device_type]").attr('disabled', 'disabled')
    }else{
        $("select[id=output_sound_device_type]").removeAttr('disabled')
    }
    
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
        $(this).toggleClass("closed");
        return false;
    })
    
    setLiveSourceConnectionOverrideListener()
    
    showErrorSections()
    setInterval('checkLiquidsoapStatus()', 1000)
    
    // qtip for help text
    $(".override_help_icon").qtip({
        content: {
            text: "If Airtime is behind a router or firewall, you may need to configure port forwarding and this field information will be incorrect. In this case you will need to manually update this field so it shows the correct host/port/mount that your DJ's need to connect to. The allowed range is between 1024 and 49151. For more detail, please read the <a target=\"_blank\" href=\"http://www.sourcefabric.org/en/airtime/manuals/\">Airtime manual</a>."
        },
        hide: {
            delay: 500,
            fixed: true
        },
        style: {
            border: {
                width: 0,
                radius: 4
            },
            classes: "ui-tooltip-dark ui-tooltip-rounded"
        },
        position: {
            my: "left bottom",
            at: "right center"
        },
    })
    
    $(".icecast_metadata_help_icon").qtip({
        content: {
            text: "Check this option to enable metadata for OGG streams (stream metadata is the track title, artist, and show name that is displayed in an audio player). VLC and mplayer have a serious bug when playing an OGG/VORBIS stream that has metadata information enabled: they will disconnect from the stream after every song. If you are using an OGG stream and your listeners do not require support for these audio players, then feel free to enable this option."
        },
        hide: {
            delay: 500,
            fixed: true
        },
        style: {
            border: {
                width: 0,
                radius: 4
            },
            classes: "ui-tooltip-dark ui-tooltip-rounded"
        },
        position: {
            my: "left bottom",
            at: "right center"
        },
    })
    
    $("#auto_transition_help").qtip({
        content: {
            text: "Check this box to automatically switch off Master/Show source upon source disconnection."
        },
        hide: {
            delay: 500,
            fixed: true
        },
        style: {
            border: {
                width: 0,
                radius: 4
            },
            classes: "ui-tooltip-dark ui-tooltip-rounded"
        },
        position: {
            my: "left bottom",
            at: "right center"
        },
    })
    
    $("#auto_switch_help").qtip({
        content: {
            text: "Check this box to automatically switch on Master/Show source upon source connection."
        },
        hide: {
            delay: 500,
            fixed: true
        },
        style: {
            border: {
                width: 0,
                radius: 4
            },
            classes: "ui-tooltip-dark ui-tooltip-rounded"
        },
        position: {
            my: "left bottom",
            at: "right center"
        },
    })
    
    $(".stream_username_help_icon").qtip({
        content: {
            text: "If your Icecast server expects a username of 'source', this field can be left blank."
        },
        hide: {
            delay: 500,
            fixed: true
        },
        style: {
            border: {
                width: 0,
                radius: 4
            },
            classes: "ui-tooltip-dark ui-tooltip-rounded"
        },
        position: {
            my: "left bottom",
            at: "right center"
        },
    })
    
    $(".master_username_help_icon").qtip({
        content: {
            text: "If your live streaming client does not ask for a username, this field should be 'source'."
        },
        hide: {
            delay: 500,
            fixed: true
        },
        style: {
            border: {
                width: 0,
                radius: 4
            },
            classes: "ui-tooltip-dark ui-tooltip-rounded"
        },
        position: {
            my: "left bottom",
            at: "right center"
        },
    })
});
