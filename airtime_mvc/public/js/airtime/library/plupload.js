$(document).ready(function() {
	
    var uploader;
	var self = this;
	self.uploadFilter = "all";
	
	self.IMPORT_STATUS_CODES = {
		0 : { message: $.i18n._("Successfully imported")},
		1 : { message: $.i18n._("Pending import")},
		2 : { message: $.i18n._("Import failed.")},
		UNKNOWN : { message: $.i18n._("Unknown")}
	};
	if (Object.freeze) {
		Object.freeze(self.IMPORT_STATUS_CODES);
	}

	$("#plupload_files").pluploadQueue({
		// General settings
		runtimes        : 'gears, html5, html4',
		url             :  baseUrl+'rest/media',
		//chunk_size      : '5mb', //Disabling chunking since we're using the File Upload REST API now
		unique_names    : 'true',
		multiple_queues : 'true',
		filters : [
			{title: "Audio Files", extensions: "ogg,mp3,oga,flac,wav,m4a,mp4,opus"}
		],
                multipart_params : {
                    "csrf_token" : $("#csrf").attr('value'),
                }
	});

	uploader = $("#plupload_files").pluploadQueue();

	uploader.bind('FileUploaded', function(up, file, json) 
	{
		//Refresh the upload table:
		self.recentUploadsTable.fnDraw(); //Only works because we're using bServerSide
		//In DataTables 1.10 and greater, we can use .fnAjaxReload()
	});
	
	var uploadProgress = false;
	
	uploader.bind('QueueChanged', function(){
        uploadProgress = (uploader.files.length > 0);
 	});
	
	uploader.bind('UploadComplete', function(){
		uploadProgress = false;
	});
	
	$(window).bind('beforeunload', function(){
		if(uploadProgress){
            return sprintf($.i18n._("You are currently uploading files. %sGoing to another screen will cancel the upload process. %sAre you sure you want to leave the page?"),
                    "\n", "\n");
		}
	});
	
	self.renderImportStatus = function ( data, type, full ) {
		if (typeof data !== "number") {
			console.log("Invalid data type for the import_status.");
			return;
		}
		var statusStr = self.IMPORT_STATUS_CODES.UNKNOWN.message;
		var importStatusCode = data;
		if (self.IMPORT_STATUS_CODES[importStatusCode]) {
			statusStr = self.IMPORT_STATUS_CODES[importStatusCode].message;
		};
	
        return statusStr;
    };
    
	self.renderFileActions = function ( data, type, full ) {
		if (full.import_status == 0) {
			return '<a class="deleteFileAction">' + $.i18n._('Delete from Library') + '</a>';			
		} else if (full.import_status == 1) {
			//No actions for pending files
			return $.i18n._('N/A'); 
		} else { //Failed downloads
			return '<a class="deleteFileAction">' + $.i18n._('Clear') + '</a>';			
		}
	};
	 
    $("#recent_uploads_table").on("click", "a.deleteFileAction", function () {
    	//Grab the file object for the row that was clicked.
    	// Some tips from the DataTables forums:
        //   fnGetData is used to get the object behind the row - you can also use
        //   fnGetPosition if you need to get the index instead
    	file = $("#recent_uploads_table").dataTable().fnGetData($(this).closest("tr")[0]);
    	
    	$.ajax({
    		  type: 'DELETE',
    		  url: 'rest/media/' + file.id + "?csrf_token=" + $("#csrf").attr('value'),
    		  success: function(resp) {
    			  self.recentUploadsTable.fnDraw();
    		  },
    		  error: function() {
    			  alert($.i18n._("Error: The file could not be deleted. Please try again later."));
    		  }
    		});
    });
    
	self.setupRecentUploadsTable = function() {
		recentUploadsTable = $("#recent_uploads_table").dataTable({
            "bJQueryUI": true,
			"bProcessing": false,
			"bServerSide": true,
			"sAjaxSource": '/Plupload/recent-uploads/format/json',
			"sAjaxDataProp": 'files',
			"bSearchable": false,
			"bInfo": true,
			//"sScrollY": "200px",
			"bFilter": false,
			"bSort": false,
			"sDom": '<"H"l>frtip',
			"bPaginate" : true,
            "sPaginationType": "full_numbers",
			"aoColumns": [
	   		   { "mData" : "artist_name", "sTitle" : $.i18n._("Creator") },
			   { "mData" : "track_title", "sTitle" : $.i18n._("Title") },
			   { "mData" : "import_status", "sTitle" : $.i18n._("Import Status"), 
			      "mRender": self.renderImportStatus
			   },
			   { "mData" : "utime", "sTitle" : $.i18n._("Uploaded") },
			   { "mData" : "id", "sTitle" : $.i18n._("Actions"),
				      "mRender": self.renderFileActions
			   }
			 ],
			 "fnServerData": function ( sSource, aoData, fnCallback ) {
				/* Add some extra data to the sender */
				aoData.push( { "name": "uploadFilter", "value": self.uploadFilter } );
				$.getJSON( sSource, aoData, function (json) { 
					fnCallback(json);
					if (json.files) {
						var areAnyFileImportsPending = false;
						for (var i = 0; i < json.files.length; i++) {
							//console.log(file);
							var file = json.files[i];
							if (file.import_status == 1)
							{
								areAnyFileImportsPending = true;
							}
						}
						if (areAnyFileImportsPending) {
							//alert("pending uploads, starting refresh on timer");
							self.startRefreshingRecentUploads();
						} else {
							self.stopRefreshingRecentUploads();
						}
					}
				} );
			 }
		});
		
		return recentUploadsTable;
	};
	
	self.startRefreshingRecentUploads = function()
	{
		if (self.isRecentUploadsRefreshTimerActive()) { //Prevent multiple timers from running
			return;
		}
		self.recentUploadsRefreshTimer = setInterval("self.recentUploadsTable.fnDraw()", 3000);
	};
	
	self.isRecentUploadsRefreshTimerActive = function()
	{
		return (self.recentUploadsRefreshTimer != null);
	};
	
	self.stopRefreshingRecentUploads = function()
	{
		clearInterval(self.recentUploadsRefreshTimer);
		self.recentUploadsRefreshTimer = null;
	};
	
	$("#upload_status_all").click(function() {
		self.uploadFilter = "all";
		self.recentUploadsTable.fnDraw();
	});
	$("#upload_status_pending").click(function() {
		self.uploadFilter = "pending";
		self.recentUploadsTable.fnDraw();
	});
	$("#upload_status_failed").click(function() {
		self.uploadFilter = "failed";
		self.recentUploadsTable.fnDraw();
	});

	//Create the recent uploads table.
	self.recentUploadsTable = self.setupRecentUploadsTable();

	//$("#recent_uploads_table.div.fg-toolbar").prepend('<b>Custom tool bar! Text/images etc.</b>');
});
