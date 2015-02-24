function showErrorSections() {
    if($("#mixcloud-settings .errors").length > 0) {
        $("#mixcloud-settings").show();
        $(window).scrollTop($("#mixcloud-settings .errors").position().top);
    }

    if($("#soundcloud-settings .errors").length > 0) {
        $("#soundcloud-settings").show();
        $(window).scrollTop($("#soundcloud-settings .errors").position().top);
    }
    
    if($("#email-server-settings .errors").length > 0) {
        $("#email-server-settings").show();
        $(window).scrollTop($("#email-server-settings .errors").position().top);
    }
    
    if($("#livestream-settings .errors").length > 0) {
        $("#livestream-settings").show();
        $(window).scrollTop($("#livestream-settings .errors").position().top);
    }
}

function setConfigureMailServerListener() {
    var configMailServer = $("#configureMailServer");
    configMailServer.click(function(event){
        setMailServerInputReadonly();
    });
    
    var msRequiresAuth = $("#msRequiresAuth");
    msRequiresAuth.click(function(event){
        setMsAuthenticationFieldsReadonly($(this));
    });
}

function setEnableSystemEmailsListener() {
    var enableSystemEmails = $("#enableSystemEmail");
    enableSystemEmails.click(function(event){
        setSystemFromEmailReadonly();
    });
}

function setSystemFromEmailReadonly() {
    var enableSystemEmails = $("#enableSystemEmail");
    var systemFromEmail = $("#systemEmail");
    if ($(enableSystemEmails).is(':checked')) {
        systemFromEmail.removeAttr("readonly");	
    } else {
        systemFromEmail.attr("readonly", "readonly");
    }	
}

function setMailServerInputReadonly() {
    var configMailServer = $("#configureMailServer");
    var mailServer = $("#mailServer");
    var port = $("#port");
    var requiresAuthCB = $("#msRequiresAuth");
    
    if (configMailServer.is(':checked')) {
        mailServer.removeAttr("readonly");
        port.removeAttr("readonly");
        requiresAuthCB.parent().show();
    } else {
        mailServer.attr("readonly", "readonly");
        port.attr("readonly", "readonly");
        requiresAuthCB.parent().hide();
    }
    
    setMsAuthenticationFieldsReadonly(requiresAuthCB);
}

/*
 * Enable/disable mail server authentication fields
 */
function setMsAuthenticationFieldsReadonly(ele) {
    var email = $("#email");
    var password = $("#ms_password");
    var configureMailServer = $("#configureMailServer");
    
    if (ele.is(':checked') && configureMailServer.is(':checked')) {
        email.removeAttr("readonly");
        password.removeAttr("readonly");
    } else if (ele.not(':checked') || configureMailServer.not(':checked')) {
        email.attr("readonly", "readonly");
        password.attr("readonly", "readonly");
    }
}

function setSoundCloudCheckBoxListener() {
    var subCheckBox= $("#UseSoundCloud,#SoundCloudDownloadbleOption");
    var mainCheckBox= $("#UploadToSoundcloudOption");
    subCheckBox.change(function(e){
        if (subCheckBox.is(':checked')) {
            mainCheckBox.attr("checked", true);
        }
    });

    mainCheckBox.change(function(e){
         if (!mainCheckBox.is(':checked')) {
            $("#UseSoundCloud,#SoundCloudDownloadbleOption").attr("checked", false);
        }   
    });
}

function setMixcloudCheckBoxListener() {
    var subCheckBox= $("#UseMixcloud");
    var mainCheckBox= $("#UploadToMixcloudOption");
    subCheckBox.change(function(e){
        if (subCheckBox.is(':checked')) {
            mainCheckBox.attr("checked", true);
        }
    });

    mainCheckBox.change(function(e){
         if (!mainCheckBox.is(':checked')) {
            $("#UseMixcloud").attr("checked", false);
        }   
    });
}

function connectToMixCloud() {
    newwindow=window.open('/mixcloud/authorize', 'mixcloud','height=600,width=1000');
	if (window.focus) {
	    newwindow.focus()
	}
	return false;
}

function disconnectFromMixCloud() {
    newwindow=window.open('/mixcloud/deauthorize', 'mixcloud','height=600,width=1000');
	if (window.focus) {
	    newwindow.focus()
	}
	return false;
}

$(document).ready(function() {

    $('.collapsible-header').live('click',function() {
        $(this).next().toggle('fast');
        $(this).toggleClass("closed");
        return false;
    }).next().hide();
    
    $('#pref_save').live('click', function() {
        var data = $('#pref_form').serialize();
        var url = baseUrl+'Preference/index';
        
        $.post(url, {format: "json", data: data}, function(json){
            $('#content').empty().append(json.html);
            setTimeout(removeSuccessMsg, 5000);
            showErrorSections();
            setMailServerInputReadonly();
            setConfigureMailServerListener();
            setEnableSystemEmailsListener();
        });
    });

    $('#ConnectToMixcloud').click(function() {
         connectToMixCloud();
    });

    $('#DisconnectFromMixcloud').click(function() {
         disconnectFromMixCloud();
    });

    showErrorSections();
    
    setSoundCloudCheckBoxListener();
    setMixcloudCheckBoxListener();
    setMailServerInputReadonly();
    setSystemFromEmailReadonly();
    setConfigureMailServerListener();
    setEnableSystemEmailsListener();
});
