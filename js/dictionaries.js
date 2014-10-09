(function($){

	$(document).ready(function(){
	
		var var4this,
				wordNid,
				parentTr,
				wasEmpty,
				blockSystemMain = $('#block-system-main .inner .content');
		
				//userId = Drupal.settings.movie.userId,
				//userAllowedToAddWords = Drupal.settings.movie.userAllowedToAddWords;		

/*Dictionary content load=====================================================================================*/			
		$('a.accordion-line-load').live('click',
			function(){
				var4this = this;
				var hrefToLoad;
				var linkToLoad;
				var contentDiv = $(var4this).parent('h3').next('.ui-accordion-content');
				if(!contentDiv.size()) {
					contentDiv = $(var4this).parents('.ui-accordion-content');					
					linkToLoad = $(var4this).parents('.ui-accordion-content').prev('h3').find('a');
					hrefToLoad = linkToLoad.attr('href');
					//contentDiv.html('');
				}
				else {
					hrefToLoad = $(var4this).attr('href');
				}
				if(!contentDiv.find('table').size() || linkToLoad !== undefined) {
					$.ajax({
						url: 'http://' + document.domain + hrefToLoad,
						success: function(data){
							//if(linkToLoad) contentDiv.find('table').hide();
							contentDiv.html(data);
							//if(linkToLoad) contentDiv.find('table').fadeIn('slow');
							$('.flag-working-dict .flag-action').attr('title','Add to working vocabulary');
							$('.flag-working-dict .unflag-action').attr('title','Delete from working vocabulary');
						}
					});
				}
			return false;
		});
/*END of Dictionary content load=====================================================================================*/			
/*Dictionary actions behaviors=====================================================================================*/			
		$('.dict-import-link').live('click',
			function(){
				var4this = this;
				if($(var4this).hasClass('link-enabled')) {
					if(Drupal.settings.movie.userId && Drupal.settings.movie.userAllowedToAddWords) {
						$.ajax({
							url: 'http://' + document.domain + $(var4this).attr('href'),
							success: function(data){
								$(var4this).addClass('link-disabled').attr('href', '').attr('title', 'You have already imported this vocab');
								
								var h3TextObj = $(var4this).parents('.ui-accordion-content:first').prev('h3').find('a span.vocab-imported-count');
								var h3TextLine = h3TextObj.text();
								var importCountInt = parseInt(h3TextLine);
								h3TextObj.text(++importCountInt);						
							}					
					});
				}	
			}
			
			return false;
		});
		
		$('.dict-delete-link').live('click',
			function(){
				var4this = this;				
				$.ajax({
					url: 'http://' + document.domain + $(var4this).attr('href'),
					success: function(data){
						$('.ui-accordion-content-active, .ui-state-active').remove();
						if(!blockSystemMain.find('.ui-accordion-content').size()) {
							blockSystemMain.html('<p>You have no vocabs yet.</p>');
						}
					}					
			});				
			return false;
		});
/*END of Dictionary actions behaviors=====================================================================================*/			
/*Word actions behaviors=====================================================================================*/			
		$('.word-link').live('mouseover', function(){
			$('.subtitle-word-add-plus').hide();
		});
		
		$('.word-link')
			.livequery(function(){$(this)
				.each(function(){$(this).qtip({
						content: {
							text: 'Loading...',
							url: 'http://' + document.domain + '/dictionaries/word/show/subtitle/' + $(this).attr('id')
						}
					});
				});
			});
		
		$('.word-link').live('click',function(){
			return false;
		});
		
		$('.word-link').live('click', function() {
			var params = 'width=375,height=450,resizable=no,scrollbars=no,status=no';
			subs = window.open('http://' + document.domain + '/movie/' + $(this).attr('id') + '/subtitles-detached/1', 'subs', params);
			return false;
		});		
		
		$('.delete-word').live('click',
			function(){
				var4this = this;
				var lastWord = 1;
				var lastWordUI;				
				var isFilmsVocabsPage = blockSystemMain.find('.ui-accordion-content').size() > 0 ? 1 : 0;
				if(isFilmsVocabsPage) {
					lastWord = $(var4this).parents('.ui-accordion-content').find('.delete-word').size() > 1 ? 0 : 1;
					lastWordUI = lastWord;
				}
				else {
					lastWordUI = blockSystemMain.find('.delete-word').size() > 1 ? 0 : 1;
				}
				$.ajax({
					url: 'http://' + document.domain + $(var4this).attr('href') + '/' + lastWord,
					success: function(data){
						if(lastWordUI == 1) {
							if(isFilmsVocabsPage) {
								if(data) {
									$('.ui-accordion-content-active, .ui-state-active').remove();							
									if(!blockSystemMain.find('.ui-accordion-content').size()) {
										blockSystemMain.html('<p>You have no vocabs yet.</p>');
									}
								}
								else {
									$(var4this).parents('.ui-accordion-content').find('a.accordion-line-load').trigger('click');
								}
							}
							else {
								location.reload();
								if(!blockSystemMain.find('.delete-word').size()) {
									blockSystemMain.html('<p>You have no vocabs yet.</p>');
								}
							}
						}
						else {
							parentTr = $(var4this).parents('tr');
							parentTr.nextAll('tr.even').removeClass('even').addClass('odd new');
							parentTr.nextAll('tr.odd:not(.new)').removeClass('odd').addClass('even');
							parentTr.nextAll('tr').removeClass('new');							
							parentTr.remove();
						}
					}					
			});				
			return false;
		});		
/*END of Word actions behaviors=====================================================================================*/			
/*Print button behaviors=====================================================================================*/			
		$('a.print-button').live('click', function(){
			$(this).parent('div').siblings('div.dictionary-table').printArea();
			return false;
		});
/*END of Print button behaviors=====================================================================================*/			
/*Eyes behaviors=====================================================================================*/		
		$('a.eye-left').live('click', function(){
			eyeActions(this, '.lang-word', false);
			return false;			
		});				

		$('a.eye-right').live('click', function(){
			eyeActions(this, '.dict-td-translation-wraper', false);
			return false;			
		});	

		$('a.eye-translation').live('click', function(){
			eyeActions(this, '.dict-td-translation-wraper', true, '.eye-right');
			return false;			
		});			
		
		$('a.eye-word').live('click', function(){
			eyeActions(this, '.lang-word', true, '.eye-left');
			return false;			
		});		
		
		$('a.eye-notes').live('click', function(){
			eyeActions(this, '.comment-form', true);
			return false;			
		});

		$('a.eye-line').live('click', function(){
			eyeActions(this, '.lang-word,.dict-links,.dict-td-translation-wraper', false, 'a.eye-left,a.eye-right');
			return false;			
		});
		
		$('a.eye').livequery(function(){
			var4this = $(this);
			if($.cookie(var4this.attr('id'))) {
				if(var4this.hasClass('eye-word'))	{
					eyeActions(this, '.lang-word', true, '.eye-left');
				}
				else if(var4this.hasClass('eye-translation'))	{
					eyeActions(this, '.dict-td-translation-wraper', true, '.eye-right');
				}
				else if(var4this.hasClass('eye-line'))	{
					eyeActions(this, '.lang-word,.dict-links,.dict-td-translation-wraper', false, 'a.eye-left,a.eye-right');
				}
				else if(var4this.hasClass('eye-left'))	{
					
					eyeActions(this, '.lang-word', false);
				}
				else if(var4this.hasClass('eye-right'))	{
					eyeActions(this, '.dict-td-translation-wraper', false);
				}				
			}
		});		
/*ENF of Eyes behaviors=====================================================================================*/			
/*Translation block visibility behaviors=====================================================================================*/			
		$('form textarea')
			.livequery(function(){$(this).each(
				function(){
					var translationWraper = $(this).parents('.dict-td-translation-wraper');
					if($(this).val() == '' && translationWraper.find('.translation').text() != '') {
						$(this).hide();
						translationWraper.find('.collapse, .open').hide();
					}
					else if($(this).val() == '' && translationWraper.find('.translation').text() == '') {
						translationWraper.find('.add-word-note, a.translation-note').hide();
					}				
					else {
						translationWraper.find('.add-word-note, a.translation-note.open').hide();
					}
				}
			);
		});
		
		$('a.add-word-note').live('click', function(){
				$(this).siblings('.word-wraper').find('textarea').slideDown().focus();
				$(this).hide();
				return false;
			}
		);

		$('a.translation-note').live('click', function(){
				if($(this).hasClass('collapse')) {
					$(this).siblings('.word-wraper').find('textarea').slideUp();
					$(this).hide();
					$(this).siblings('a.translation-note.open').show();
				}
				else if($(this).hasClass('open')) {
					$(this).siblings('.word-wraper').find('textarea').slideDown();
					$(this).hide();
					$(this).siblings('a.translation-note.collapse').show();
				}
				return false;
			}
		);

		$('form .form-submit').livequery(function(){$(this).hide()});
		$('form textarea')
			.livequery(function(){$(this)
				.focus(function(){
					wasEmpty = $(this).val();
					$(this).next('.form-submit').css('width', '100').slideDown();
				})
				.blur(function(){
					//var4this = $(this);
					var translationWraper = $(this).parents('.dict-td-translation-wraper');
					$(this).parent('form').find('.form-submit').slideUp();
					$(this).parent('form').next('a.add-word-note').hide();				
					if($(this).val() == '' && wasEmpty == '' && translationWraper.find('.translation').text() != '') {
						$(this).hide();
						translationWraper.find('.add-word-note').show()
									 .siblings('a.translation-note').hide();
					}
					else if($(this).val() != '' && translationWraper.find('.translation').text() != '') {
						translationWraper.find('a.translation-note.collapse').show();
					}
				});
			});
/*END of Translation block visibility behaviors=====================================================================================*/
/*Translation notes save behaviors=====================================================================================*/			
		$('.word-wraper form').live('submit',
			function(){
				return false;
		});				
		
		$('.word-wraper form .form-submit').live('click',
			function(){
				var4this = this;
				var commentBody = $(var4this).prev('textarea').val();
				var authorName = $(var4this).prev('#edit-author').val() ? $(var4this).prev('#edit-author').val() : '';
				wordNid = $(var4this).parents('.word-wraper').attr('id').match(/\d+$/);				
				wordNid = parseInt(wordNid, 10); 
				$.ajax({
					url: 'http://' + document.domain + '/dictionaries/word/add/comment/' + wordNid + '/' + commentBody + '/' + authorName,
					beforeSend: function(){
					},
					success: function(data){
					}
				});
				if(commentBody == '' && $(var4this).parents('.dict-td-translation-wraper').find('.translation').text() != '') {
					$(var4this).prev('textarea').slideUp()
						.parents('.dict-td-translation-wraper')
							.find('.add-word-note').show()
							.siblings('a.translation-note').hide();
				}	
				return false;
			}
		);				
/*END of Translation notes save behaviors=====================================================================================*/			

	});
	
		function eyeActions(context, actionSelector, isColumn, lineSelector) {
			var4this = $(context);
			if(isColumn){
				var actionSelectorParentTd = $(actionSelector).parent('td');
				actionSelectorParentTd.css('width', actionSelectorParentTd.width());
				//var toChange = var4this.parents('table').find(actionSelector);
				
				var toChange = var4this.parents('table').find(actionSelector).map(function(){
					var idToCheckCookie;
					if(lineSelector == '.eye-left') {
						idToCheckCookie = $(this).parents('td').next('td').find(lineSelector).attr('id');
					}
					else {
						idToCheckCookie = $(this).parents('td').prev('td').find(lineSelector).attr('id');
					}
					var idLineToCheckCookie = $(this).parents('td').prevAll('td').find('.eye-line').attr('id');					
					return !$.cookie(idToCheckCookie) && !$.cookie(idLineToCheckCookie) ? this : null;
				});

				lineSelector = $(lineSelector);
			}
			else {
				var toChange = var4this.parents('td').siblings('td').find(actionSelector);
				if(lineSelector) {
					lineSelector = var4this.parents('td').siblings('td').find(lineSelector);
				}
			}
			if(var4this.hasClass('eye-see')) {
				toChange.hide();
				$.cookie(var4this.attr('id'), 1);
				var4this.removeClass('eye-see').addClass('eye-dont-see');
				if(lineSelector) {				
					lineSelector.removeClass('eye-see').addClass('eye-dont-see') ;
				}
			}
			else {
				if((var4this.hasClass('eye-left') && $.cookie(var4this.parents('table').find('.eye-word').attr('id')))
					|| (var4this.hasClass('eye-right') && $.cookie(var4this.parents('table').find('.eye-translation').attr('id')))) {
					toChange.hide();
				}
				else {
					toChange.show();
					$.cookie(var4this.attr('id'), null);
					var4this.removeClass('eye-dont-see').addClass('eye-see');
					if(lineSelector) {			
						lineSelector.livequery(function(){
							if(!$.cookie($(this).attr('id'))) {
								$(this).removeClass('eye-dont-see').addClass('eye-see');
							}	
						});					
					}
				}
			}
		}
		
		
})(jQuery);

