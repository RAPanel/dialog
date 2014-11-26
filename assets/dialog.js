$(function() {

	var options = {
		containerSelector: null,
		dialogId: null,
		url: null,
		sendFormId: null,
		invalidateUrl: null,
		autoResize: null
	};

	var lpLoading = false;

	var Plugin = function(opts) {
		options = $.extend(options, opts);
		Plugin.longPolling();

		$(document).on('keydown', '#' + options.sendFormId + ' textarea', function(e) {
			if(e.keyCode == 13 && e.ctrlKey) {
				$(this).closest('form').submit();
			}
		});

		$(document).on('submit', '#' + options.sendFormId, function(e) {
			e.preventDefault();
			var $form = $(this);
			var formData = new FormData($form.get(0));
			var $container = $(options.containerSelector);
			$container.append('<div class="message-loading"></div>');
			console.log(formData);
			$.ajax({
				url: $form.attr('action'),
				method: 'POST',
				dataType: 'json',
				cache: false,
				processData: false,
				contentType: false,
				data: formData,
				success: function(data) {
					Plugin.newMessages(data);
					if(!lpLoading) {
						Plugin.longPolling();
					}
				},
				error: function() {
					console.error('Send error');
				}
			});
			$form.reset();
		});

		if(options.autoResize) {
			var $form = $('#' + options.sendFormId);
			var $msgContainer = $(options.containerSelector);
			var $parentContainer = $msgContainer.parent();
			if($form.length && $msgContainer.length && $parentContainer.length) {
				$(window).on('resize', function() {
					$parentContainer.height($(window).height() - $parentContainer.offset().top - $form.outerHeight());
				});
				$(window).on('lastMessageScroll', function() {
					var scrollHeight = $msgContainer.prop('scrollHeight');
					if(scrollHeight)
						$msgContainer.scrollTop(scrollHeight - $msgContainer.height());
				});
				$(window).trigger('resize');
				$(window).trigger('lastMessageScroll');
			}
		}

	};

	Plugin.longPolling = function() {
		var $container = $(options.containerSelector);
		var last = $container.find("[data-id]").last().attr('data-id');
		if(last) {
			lpLoading = true;
			$.ajax({
				url: options.url,
				data: {last: last},
				method: 'GET',
				dataType: 'json',
				success: function (data) {
					lpLoading = false;
					Plugin.newMessages(data, false);
					Plugin.longPolling();
				},
				error: function () {
					lpLoading = false;
					console.error('IM error');
				}
			});
		}
	};

	Plugin.newMessages = function(data, scroll) {
		scroll = typeof scroll == 'undefined' ? true : scroll;
		var $container = $(options.containerSelector);
		if(typeof data.messages != 'undefined') {
			if(typeof data.messages[options.dialogId] != 'undefined') {
				$container.find('.message-loading').remove();
				$.each(data.messages[options.dialogId], function (id, content) {
					if ($container.find('[data-id="' + id + '"]').length == 0) {
						$container.append(content);
					}
				});
				if(scroll)
					$(window).trigger('lastMessageScroll');
			}
		}
	};

	$.fn.imDialog = Plugin;

});