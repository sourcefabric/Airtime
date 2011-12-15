/**
*
*	Schedule Dialog creation methods.
*
*/

//dateText mm-dd-yy
function startDpSelect(dateText, inst) {
	var time, date;

	time = dateText.split("-");
	date = new Date(time[0], time[1] - 1, time[2]);

	$("#add_show_end_date").datepicker("option", "minDate", date);
    $('input[name^="add_show_rebroadcast_absolute_date"]').datepicker("option", "minDate", date);
    if (inst.input)
        inst.input.trigger('change');
}

function endDpSelect(dateText, inst) {
	var time, date;
    
	time = dateText.split("-");
	date = new Date(time[0], time[1] - 1, time[2]);

	if (inst.input)
        inst.input.trigger('change');
}

function createDateInput(el, onSelect) {
	var date;

	el.datepicker({
			minDate: adjustDateToServerDate(new Date(), timezoneOffset),
			onSelect: onSelect,
			dateFormat: 'yy-mm-dd'
		});
}

function autoSelect(event, ui) {

    $("#add_show_hosts-"+ui.item.index).attr("checked", "checked");
	event.preventDefault();
}

function findHosts(request, callback) {
	var search, url;

	url = "/User/get-hosts";
	search = request.term;

	var noResult = new Array();
    noResult[0] = new Array();
    noResult[0]['value'] = $("#add_show_hosts_autocomplete").val();
    noResult[0]['label'] = "No result found";
    noResult[0]['index'] = null;
    
	$.post(url,
		{format: "json", term: search},

		function(json) {
		    if(json.hosts.length<1){
	            callback(noResult);
	        }else{
	            callback(json.hosts);
	        }
		});

}

function beginEditShow(data){
    if(data.show_error == true){
        alertShowErrorAndReload();
        return false;
    }
    $("#add-show-form")
        .empty()
        .append(data.newForm);

    removeAddShowButton();
    setAddShowEvents();
    openAddShowForm();
}

function setAddShowEvents() {

    var form = $("#add-show-form");

	form.find("h3").click(function(){
        $(this).next().toggle();
    });

    if(!form.find("#add_show_repeats").attr('checked')) {
        form.find("#schedule-show-when > fieldset:last").hide();
        $("#add_show_rebroadcast_relative").hide();
    }
    else {
        $("#add_show_rebroadcast_absolute").hide();
    }

    if(!form.find("#add_show_record").attr('checked')) {
        form.find("#add_show_rebroadcast").hide();
    }

    if(!form.find("#add_show_rebroadcast").attr('checked')) {
        form.find("#schedule-record-rebroadcast > fieldset:not(:first-child)").hide();
    }

    form.find("#add_show_repeats").click(function(){
        $(this).blur();
        form.find("#schedule-show-when > fieldset:last").toggle();

        //must switch rebroadcast displays
        if(form.find("#add_show_rebroadcast").attr('checked')) {

            if($(this).attr('checked')){
                form.find("#add_show_rebroadcast_absolute").hide();
                form.find("#add_show_rebroadcast_relative").show();
            }
            else {
                form.find("#add_show_rebroadcast_absolute").show();
                form.find("#add_show_rebroadcast_relative").hide();
            }
        }
    });

    form.find("#add_show_record").click(function(){
        $(this).blur();
        form.find("#add_show_rebroadcast").toggle();

        //uncheck rebroadcast checkbox
        form.find("#add_show_rebroadcast").attr('checked', false);

        //hide rebroadcast options
        form.find("#schedule-record-rebroadcast > fieldset:not(:first-child)").hide();
    });

    form.find("#add_show_rebroadcast").click(function(){
        $(this).blur();
        if(form.find("#add_show_record").attr('checked')){
	        if($(this).attr('checked') && !form.find("#add_show_repeats").attr('checked')) {
	            form.find("#add_show_rebroadcast_absolute").show();
	        }
	        else if($(this).attr('checked') && form.find("#add_show_repeats").attr('checked')) {
	            form.find("#add_show_rebroadcast_relative").show();
	        }
	        else {
	            form.find("#schedule-record-rebroadcast > fieldset:not(:first-child)").hide();
	        }
    	}
    });

    form.find("#add_show_repeat_type").change(function(){
        if($(this).val() == 2) {
            form.find("#add_show_day_check-label, #add_show_day_check-element").hide();
        }
        else {
            form.find("#add_show_day_check-label, #add_show_day_check-element").show();
        }
    });

    form.find("#add_show_day_check-label").addClass("block-display");
    form.find("#add_show_day_check-element").addClass("block-display clearfix");
    form.find("#add_show_day_check-element label").addClass("wrapp-label");
    form.find("#add_show_day_check-element br").remove();

    function endDateVisibility(){
        if(form.find("#add_show_no_end").is(':checked')){
            form.find("#add_show_end_date").hide();
        } else {
            form.find("#add_show_end_date").show();
        }
    }
    endDateVisibility();
    form.find("#add_show_no_end").click(endDateVisibility);

	createDateInput(form.find("#add_show_start_date"), startDpSelect);
	createDateInput(form.find("#add_show_end_date_no_repeat"), endDpSelect);
	createDateInput(form.find("#add_show_end_date"), endDpSelect);

    form.find("#add_show_start_time").timepicker({
        amPmText: ['', ''],
        defaultTime: '00:00'
    });
    form.find("#add_show_end_time").timepicker({
        amPmText: ['', '']
    });

    form.find('input[name^="add_show_rebroadcast_date_absolute"]').datepicker({
		minDate: adjustDateToServerDate(new Date(), timezoneOffset),
		dateFormat: 'yy-mm-dd'
	});
    form.find('input[name^="add_show_rebroadcast_time"]').timepicker({
        amPmText: ['', ''],
        defaultTime: ''
    });

    form.find(".add_absolute_rebroadcast_day").click(function(){
        var li = $(this).parent().find("ul.formrow-repeat > li:visible:last").next();

        li.show();
        li = li.next();
        if(li.length === 0) {
            $(this).hide();
        }
    });

    form.find('a[id^="remove_rebroadcast"]').click(function(){
        var list = $(this).parent().parent();
        var li_num = $(this).parent().index();
        var num = list.find("li").length;
        var count = num - li_num;

        var curr = $(this).parent();
        var next = curr.next();

        for(var i=0; i<=count; i++) {
            var date = next.find('[name^="add_show_rebroadcast_date"]').val();
            curr.find('[name^="add_show_rebroadcast_date"]').val(date);
            var time = next.find('[name^="add_show_rebroadcast_time"]').val();
            curr.find('[name^="add_show_rebroadcast_time"]').val(time);

            curr = next;
            next = curr.next();
        }

        list.find("li:visible:last")
                .find('[name^="add_show_rebroadcast_date"]')
                    .val('')
                .end()
                .find('[name^="add_show_rebroadcast_time"]')
                    .val('')
                .end()
            .hide();

        list.next().show();
    });

	form.find("#add_show_hosts_autocomplete").autocomplete({
		source: findHosts,
		select: autoSelect,
        delay: 200
	});
	
	form.find("#add_show_hosts_autocomplete").keypress(function(e){
        if( e.which == 13 ){
            return false;
        }
    })

	form.find("#schedule-show-style input").ColorPicker({
        onChange: function (hsb, hex, rgb, el) {
		    $(el).val(hex);
	    },
		onSubmit: function(hsb, hex, rgb, el) {
			$(el).val(hex);
			$(el).ColorPickerHide();
		},
		onBeforeShow: function () {
			$(this).ColorPickerSetColor(this.value);
		}
	});


    form.find("#add-show-close")
		.click(function(event){
            event.stopPropagation();
            event.preventDefault();

            $("#schedule_calendar").removeAttr("style")
                .fullCalendar('render');

			$("#add-show-form").hide();
            $.get("/Schedule/get-form", {format:"json"}, function(json){
                $("#add-show-form")
                    .empty()
                    .append(json.form);

                setAddShowEvents();
            });
            makeAddShowButton();
		});

	form.find(".add-show-submit")
		.click(function(event){
            var addShowButton = $(this);
            if (!addShowButton.hasClass("disabled")){
                addShowButton.addClass("disabled");
            }
            else {
                return;
            }

            event.preventDefault();

			var data = $("form").serializeArray();

            var hosts = $('#add_show_hosts-element input').map(function() {
                if($(this).attr("checked")) {
                    return $(this).val();
                }
            }).get();

            var days = $('#add_show_day_check-element input').map(function() {
                if($(this).attr("checked")) {
                    return $(this).val();
                }
            }).get();

            var start_date = $("#add_show_start_date").val();
            var end_date = $("#add_show_end_date").val();

            $.post("/Schedule/add-show", {format: "json", data: data, hosts: hosts, days: days}, function(json){
                addShowButton.removeClass("disabled");
                if(json.form) {
                    $("#add-show-form")
                        .empty()
                        .append(json.form);

                    setAddShowEvents();

                    $("#add_show_end_date").val(end_date);
                    $("#add_show_start_date").val(start_date);
                    showErrorSections();
                }
                else {
                     $("#add-show-form")
                        .empty()
                        .append(json.newForm);

                    setAddShowEvents();
                    scheduleRefetchEvents(json);
                }
            });
		});

	// when start date/time changes, set end date/time to start date/time+1 hr
	$('#add_show_start_date, #add_show_start_time').change(function(){
		var startDate = $('#add_show_start_date').val().split('-');
		var startTime = $('#add_show_start_time').val().split(':');
        var startDateTime = new Date(startDate[0], parseInt(startDate[1], 10)-1, startDate[2], startTime[0], startTime[1], 0, 0);

		var endDate = $('#add_show_end_date_no_repeat').val().split('-');
		var endTime = $('#add_show_end_time').val().split(':');
        var endDateTime = new Date(endDate[0], parseInt(endDate[1], 10)-1, endDate[2], endTime[0], endTime[1], 0, 0);

		if(startDateTime.getTime() >= endDateTime.getTime()){
		    var duration = $('#add_show_duration').val();
	        // parse duration
		    var time = 0;
		    var info = duration.split(' ');
		    var h = parseInt(info[0], 10);
		    time += h * 60 * 60* 1000;
		    if(info.length >1 && $.trim(info[1]) !== ''){
		        var m = parseInt(info[1], 10);
		        time += m * 60 * 1000;
		    }
			endDateTime = new Date(startDateTime.getTime() + time);
		}

		var endDateFormat = endDateTime.getFullYear() + '-' + pad(endDateTime.getMonth()+1,2) + '-' + pad(endDateTime.getDate(),2);
		var endTimeFormat = pad(endDateTime.getHours(),2) + ':' + pad(endDateTime.getMinutes(),2);

		$('#add_show_end_date_no_repeat').val(endDateFormat);
		$('#add_show_end_time').val(endTimeFormat);

		// calculate duration
		calculateDuration(endDateTime, startDateTime);
	});

	// when end date/time changes, check if the changed date is in past of start date/time
	$('#add_show_end_date_no_repeat, #add_show_end_time').change(function(){
		var startDate = $('#add_show_start_date').val().split('-');
        var startTime = $('#add_show_start_time').val().split(':');
		var startDateTime = new Date(startDate[0], parseInt(startDate[1], 10)-1, startDate[2], startTime[0], startTime[1], 0, 0);

		var endDate = $('#add_show_end_date_no_repeat').val().split('-');
		var endTime = $('#add_show_end_time').val().split(':');
        var endDateTime = new Date(endDate[0], parseInt(endDate[1], 10)-1, endDate[2], endTime[0], endTime[1], 0, 0);

		if(startDateTime.getTime() > endDateTime.getTime()){
			$('#add_show_end_date_no_repeat').css('background-color', '#F49C9C');
			$('#add_show_end_time').css('background-color', '#F49C9C');
		}else{
			$('#add_show_end_date_no_repeat').css('background-color', '');
			$('#add_show_end_time').css('background-color', '');
		}

		// calculate duration
		calculateDuration(endDateTime, startDateTime);
	});

	function calculateDuration(endDateTime, startDateTime){
		var duration;
		var durationSeconds = (endDateTime.getTime() - startDateTime.getTime())/1000;
		if(isNaN(durationSeconds)){
		    duration = '1h';
		}
		else if(durationSeconds != 0){
			var durationHour = parseInt(durationSeconds/3600, 10);
			var durationMin = parseInt((durationSeconds%3600)/60, 10);
			duration = (durationHour == 0 ? '' : durationHour+'h'+' ')+(durationMin == 0 ? '' : durationMin+'m');
		}else{
			duration = '0m';
		}
		$('#add_show_duration').val(duration);
	}

	function pad(number, length) {
	    var str = '' + number;
	    while (str.length < length) {
	        str = '0' + str;
	    }

	    return str;
	}
}

function showErrorSections() {

    if($("#schedule-show-what .errors").length > 0) {
        $("#schedule-show-what").show();
    }
    if($("#schedule-show-when .errors").length > 0) {
        $("#schedule-show-when").show();
    }
    if($("#schedule-show-who .errors").length > 0) {
        $("#schedule-show-who").show();
    }
    if($("#schedule-show-style .errors").length > 0) {
        $("#schedule-show-style").show();
    }
    if($("#add_show_rebroadcast_absolute .errors").length > 0) {
        $("#schedule-record-rebroadcast").show();
        $("#add_show_rebroadcast_absolute").show();
    }
    if($("#add_show_rebroadcast_relative .errors").length > 0) {
        $("#schedule-record-rebroadcast").show();
        $("#add_show_rebroadcast_relative").show();
    }
    $('input:text').setMask()
}

$(document).ready(function() {
    $.mask.masks = $.extend($.mask.masks,{
        date:{ mask: '9999-19-39'},
        time:{ mask: '29:69'}
    })
    
    $('input:text').setMask()
	//setAddShowEvents();
});

//Alert the error and reload the page
//this function is used to resolve concurrency issue
function alertShowErrorAndReload(){
    alert("The show instance doesn't exist anymore!");
    window.location.reload();
}


$(window).resize(function(){
	var windowWidth = $(this).width();
    // margin on showform are 16 px on each side
	if(!$("#schedule-add-show").is(':hidden')){	 
        var calendarWidth = 100-(($("#schedule-add-show").width() + (16 * 4))/windowWidth*100);
        var widthPercent = parseInt(calendarWidth)+"%";
        $("#schedule_calendar").css("width", widthPercent);
	}
	
	// 200 px for top dashboard and 50 for padding on main content
	// this calculation was copied from schedule.js line 326
	var mainHeight = document.documentElement.clientHeight - 200 - 50;
	$('#schedule_calendar').fullCalendar('option', 'contentHeight', mainHeight)
	$("#schedule_calendar").fullCalendar('render');
	
});

$(window).load(function() {
    $.mask.masks = $.extend($.mask.masks,{
        date:{ mask: '9999-19-39'},
        time:{ mask: '29:69'}
    })
    
    $('input:text').setMask()
    
	setAddShowEvents();
	
});
