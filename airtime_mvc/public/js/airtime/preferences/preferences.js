function showErrorSections() {
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

$(document).ready(function() {

    $('.collapsible-header').live('click',function() {
        $(this).next().toggle('fast');
        $(this).toggleClass("closed");
        return false;
    }).next().hide();
    
    $('#pref_save').live('click', function() {
        var data = $('#pref_form').serialize();
        var url = baseUrl+'/Preference/index';
        
        $.post(url, {format: "json", data: data}, function(data){
            var json = $.parseJSON(data);
            $('#content').empty().append(json.html);
            setTimeout(removeSuccessMsg, 5000);
            showErrorSections();
        });
    });

    showErrorSections();
    
    setMailServerInputReadonly();
    setSystemFromEmailReadonly();
    setConfigureMailServerListener();
    setEnableSystemEmailsListener();
});
