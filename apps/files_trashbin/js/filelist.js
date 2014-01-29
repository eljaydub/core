/* global OC, FileList */
(function() {
	// override reload with own ajax call
	FileList.reload = function(){
		FileList.showMask();
		if (FileList._reloadCall){
			FileList._reloadCall.abort();
		}
		$.ajax({
			url: OC.filePath('files_trashbin','ajax','list.php'),
			data: {
				dir : $('#dir').val()
			},
			error: function(result) {
				FileList.reloadCallback(result);
			},
			success: function(result) {
				FileList.reloadCallback(result);
			}
		});
	};

	var oldCreateRow = FileList._createRow;
	FileList._createRow = function() {
		// FIXME: MEGAHACK until we find a better solution
		var tr = oldCreateRow.apply(this, arguments);
		tr.find('td.filesize').remove();
		return tr;
	};

	FileList._onClickBreadCrumb = function(e) {
		var $el = $(e.target).closest('.crumb'),
			index = $el.index(),
			$targetDir = $el.data('dir');
		// first one is home, let the link makes it default action
		if (index !== 0) {
			e.preventDefault();
			FileList.changeDirectory($targetDir);
		}
	};

	var oldAdd = FileList.add;
	FileList.add = function(fileData, options) {
		options = options || {};
		var dir = FileList.getCurrentDirectory();
		var dirListing = dir !== '' && dir !== '/';
		if (!dirListing) {
			fileData.displayName = fileData.name;
			fileData.name = fileData.name + '.d' + fileData.timestamp;
		}
		// show deleted time as mtime
		if (fileData.timestamp) {
			fileData.mtime = parseInt(fileData.timestamp, 10) * 1000;
		}
		return oldAdd.call(this, fileData, options);
	};

	FileList.linkTo = function(dir){
		return OC.linkTo('files_trashbin', 'index.php')+"?dir="+ encodeURIComponent(dir).replace(/%2F/g, '/');
	};

	FileList.updateEmptyContent = function(){
		var $fileList = $('#fileList');
		var exists = $fileList.find('tr:first').exists();
		$('#emptycontent').toggleClass('hidden', exists);
		$('#filestable th').toggleClass('hidden', !exists);
	};
})();
