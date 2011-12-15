$(document).ready(function() {
    var uploader;

	$("#plupload_files").pluploadQueue({
		// General settings
		runtimes : 'gears, html5, html4',
		url : '/Plupload/upload/format/json',
		chunk_size: '5mb',
		unique_names: 'true',
		multiple_queues : 'true',
		filters : [
			{title: "Audio Files", extensions: "ogg,mp3"}
		]
	});

	uploader = $("#plupload_files").pluploadQueue();

	uploader.bind('FileUploaded', function(up, file, json) {
		var j = jQuery.parseJSON(json.response);
		
		if(j.error !== undefined) {
			var row = $("<tr/>")
				.append('<td>' + file.name +'</td>')
				.append('<td>' + j.error.message + '</td>');
				
			$("#plupload_error").find("table").append(row);
		}else{
		    var tempFileName = j.tempfilepath;
		    $.get('/Plupload/copyfile/format/json/name/'+encodeURIComponent(file.name)+'/tempname/'+encodeURIComponent(tempFileName), function(json){
		        var jr = jQuery.parseJSON(json);
		        if(jr.error !== undefined) {
		            var row = $("<tr/>")
		                .append('<td>' + file.name +'</td>')
		                .append('<td>' + jr.error.message + '</td>');
		                
		            $("#plupload_error").find("table").append(row);
		        }
		    });
		}
	});
	
	var uploadProgress = false;
	
	uploader.bind('QueueChanged', function(){
		if(uploader.files.length > 0){
			uploadProgress = true;
		}else{
			uploadProgress = false;
		}
	});
	
	uploader.bind('UploadComplete', function(){
		uploadProgress = false;
	});
	
	$(window).bind('beforeunload', function(){
		if(uploadProgress){
			if(!confirm("You are currently uploading files.\nGoing to another screen will cancel the upload process.\nAre you sure you want to cancel the upload process and go to the screen you clicked on?")){
				return false;
			}
		}
	});

});
