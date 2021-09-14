function DataTable(element){
	element.not('.initialized').addClass('initialized').DataTable({
		destroy: true, 
		"pageLength": 25,
		"lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "Все"]],
		'initComplete': function (settings, json){
			$(this.api().column(0).footer()).html('Всего');
			this.api().columns('.sum').every(function(){
				var column = this;
				var intVal = function ( i ) {
					return typeof i === 'string' ?
						i.replace(/[\$,]/g, '.').replace(/[\$ ,]/g, '')*1 :
						typeof i === 'number' ?
							parseFloat(i) : 0;
				}
				var sum = column
					.data()
					.reduce(function (a, b) { 
						return intVal(a) + intVal(b);
					 });

				$(column.footer()).html(new Intl.NumberFormat('ru').format(intVal(sum).toFixed(2)));
			});
		}	
	});	
}

$(function() 
{	
	function check_link()
	{
		$('.selectpicker').selectpicker();
		DataTable($('.dataTable'));	
		var links = $('form[action="###"], form[disabled]');
		if(links.length){
			$.each( links, function( key, value ) {
				$(value).find('a[href="#"]').hide();
				$(value).find('select').prop('disabled', true);
				$(value).find('textarea').prop('disabled', true);
				$(value).find('input').prop('disabled', true);
				$(value).find('button').prop('disabled', true);
			});		
		}	
	}
	
	check_link();

	$('body').on('click', '.minimized, #faq img',function(event) {
		event.preventDefault();
		var i_path = ($(this).attr('src')?$(this).attr('src'):$(this).attr('href'));
		
		if($(this).find('img')){
			$("#main_modal .modal-content").html('<center><a target="_blank" href="'+i_path+'"><img src="'+i_path+'" alt="'+i_path+'" /></a></center>');	
		}
		else{
			switch(i_path.split('.').pop()){
				case 'mp4':
					$("#main_modal .modal-content").html('<center><video controls autoplay><source src="'+i_path+'"></video></center>');
				break;
				case 'png':
				case 'jpg':
				case 'gif':
				case 'jpeg':
					$("#main_modal .modal-content").html('<center><a target="_blank" href="'+i_path+'"><img src="'+i_path+'" alt="'+i_path+'" /></a></center>');
				break;
				default:
					$("#main_modal .modal-content").load(i_path);
				break;
			}
		}
		
		$("#main_modal").modal('show');
	});
		
	
	$('body').on('click', '#all-region', function(event) {
		if($(this).is(':checked'))
			$('#region option').attr('selected','selected');
		else
			$('#region option').removeAttr('selected');
	});
	
	var modal_link = null;
	
	$('body').on('click', '#container [data-toggle="modal"]', function(event) {
		if($(this).attr('href'))
			modal_link = $(this);
	});
			
	$('body').on('click', '.modal-content.ajax a', function(event) {
		if($(this).attr('href').indexOf('#') && $(this).attr('target')!='_blank' && (!$(this).data('toggle') || $(this).data('toggle')!='modal')){
			event.preventDefault();
			let update = $(this).hasClass('ajax_update');
			$(this).parents('.modal-content').first().html('<span class="glyphicon-refresh-animate glyphicon glyphicon-refresh"></span>').load($(this).attr('href'),  function() { //Данные отправлены успешно
				if(update){
					$('#container').html('<span class="glyphicon-refresh-animate glyphicon glyphicon-refresh"></span>').load(window.location.href, function() {
						check_link();
						$('body').animate({ scrollTop: $('#container').find(modal_link).offset().top }, 'slow');
					});
				}
			});
		}
	});		
	
	$('body').on('submit', '.modal-content.ajax form', function(event) {
		event.preventDefault();
		
		var form = $(this);
		var content = $(form).parents('.modal-content').first();

		content.html('<span class="glyphicon-refresh-animate glyphicon glyphicon-refresh"></span>');
		$.ajax({
			url:     form.attr('action'), 
			type:    form.attr('method'), 
			data: 	 (form.attr('method') == 'GET'?form.serialize():new FormData(form[0])),  
			processData: false,
			contentType: false,
			success: function(response) { //Данные отправлены успешно
				if($(form).hasClass('ajax_update')){
					$('#container').html('<span class="glyphicon-refresh-animate glyphicon glyphicon-refresh"></span>').load(window.location.href, function() {
						content.html(response);
						check_link();
						$('body').animate({ scrollTop: $('#container').find(modal_link).offset().top }, 'slow');
					});
				}
				else
					content.html(response);
			},				
		});
	});	

	$('body').on('loaded.bs.modal', '.modal', function(event) {
		check_link();
	});	
	
/* 	$('body').on('show.bs.modal', '.modal', function(event) {
		$('.modal-backdrop').remove();
	}); */
	
	$( document ).ajaxError(function( event, jqxhr, settings, exception ) {
		if(jqxhr.responseText)
			alert(jqxhr.responseText);
		else
			alert('неожиданный ответ');
		
		$('.modal').modal('hide');
	});
	
	$('body').on('hidden.bs.modal', '.modal', function(event) 
	{
		$(this).removeData('bs.modal');
		$(this).find('.modal.in').modal('hide');
	});

	$('body').on('show.bs.modal', '.modal', function(event) {
	
		var button = $(event.relatedTarget);
		if(button.attr('href') && button.attr('href')!='#'){
			$(button.data('target')+' .modal-content').html('<span class="glyphicon-refresh-animate glyphicon glyphicon-refresh"></span>');
		}
		if($(this).closest('.modal').length){
			$(this).closest('.modal').animate({ scrollTop: $(this).offset().top }, 'slow');			
		}
	});
	
	$('[data-toggle="tooltip"]').tooltip();
	
	/* Локализация datepicker */
	$.datepicker.regional['ru'] = {
		closeText: 'Закрыть',
		prevText: 'Предыдущий',
		nextText: 'Следующий',
		currentText: 'Сегодня',
		monthNames: ['Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'],
		monthNamesShort: ['Янв','Фев','Мар','Апр','Май','Июн','Июл','Авг','Сен','Окт','Ноя','Дек'],
		dayNames: ['воскресенье','понедельник','вторник','среда','четверг','пятница','суббота'],
		dayNamesShort: ['вск','пнд','втр','срд','чтв','птн','сбт'],
		dayNamesMin: ['Вс','Пн','Вт','Ср','Чт','Пт','Сб'],
		weekHeader: 'Не',
		dateFormat: 'dd.mm.yy',
		firstDay: 1,
		isRTL: false,
		showMonthAfterYear: false,
		yearSuffix: ''
	};
	$.datepicker.setDefaults($.datepicker.regional['ru']);

	/* Локализация timepicker */
	$.timepicker.regional['ru'] = {
		timeOnlyTitle: 'Выберите время',
		timeText: 'Время',
		hourText: 'Часы',
		minuteText: 'Минуты',
		secondText: 'Секунды',
		millisecText: 'Миллисекунды',
		timezoneText: 'Часовой пояс',
		currentText: 'Сейчас',
		closeText: 'Закрыть',
		timeFormat: 'HH:mm',
		amNames: ['AM', 'A'],
		pmNames: ['PM', 'P'],
		isRTL: false
	};
	$.timepicker.setDefaults($.timepicker.regional['ru']);

	
	if($('#region').length){
		$('#region').closest('form').on("submit", function(){
			if(!$('#region select option:selected').val()){
				alert('Выберите регион');
				return false;
			}	
		});
	}
		
	if($('.datetimepicker').length){
		$('.datetimepicker').closest('form').on("submit", function(){
			if($($('.datetimepicker')[0]).val() && $($('.datetimepicker')[0]).val() == $($('.datetimepicker')[1]).val()){
				alert('Даты должны быть на разное время (дни)');
				return false;
			}	
		});
	}
	
	$('body').on('mouseenter', '.datetimepicker', function(event) {
		if(!$(this).hasClass('hasDatepicker')){
			$(this).datetimepicker({ 
				dateFormat: "yy-mm-dd",
				beforeShow: function(e){
					if($(e).data('days'))
						$(e).datepicker( "option", "days", $(e).data('days') );
					
					if($(e).data('max'))
						$(e).datepicker( "option", "maxDate", $(e).data('max') );
				},		
			});
		}
	});
	
	$('body').on('mouseenter', '.datepicker', function(event) {
		if(!$(this).hasClass('hasDatepicker')){
			$(this).datepicker({ 
				dateFormat: "yy-mm-dd",
				beforeShow: function(e){
					if($(e).data('days'))
						$(e).datepicker( "option", "days", $(e).data('days') );
					
					if($(e).data('max'))
						$(e).datepicker( "option", "maxDate", $(e).data('max') );		
					
					if($(e).data('min'))
						$(e).datepicker( "option", "minDate", $(e).data('min') );
				},			
				beforeShowDay: function(date){
					if($(this).datepicker( "option", "days"))
					{
						let string = jQuery.datepicker.formatDate('yy-mm-dd', date);
						return [$(this).datepicker( "option", "days").indexOf(string) != -1];
					}
					else
						return [true, '', 'отчет открыт'];
				} 
			});
		}
	});
	
	function DoubleScroll(element) {
		var scrollbar = document.createElement('div');
		scrollbar.appendChild(document.createElement('div'));
		scrollbar.style.overflow= 'auto';
		scrollbar.style.overflowY= 'hidden';
		scrollbar.style.height = '14px';
		scrollbar.id  = 'scrollbar';
		scrollbar.firstChild.style.width= element.scrollWidth+'px';
		scrollbar.firstChild.appendChild(document.createTextNode('\xA0'));
		scrollbar.onscroll= function() {
			element.scrollLeft= scrollbar.scrollLeft;
		};
		element.onscroll= function() {
			scrollbar.scrollLeft = element.scrollLeft;
		};
		element.parentNode.insertBefore(scrollbar, element);
		element.onscroll();
	}
	
/* 	if($('#fixed').length){
		if($('#fixed').hasClass('double'))
			DoubleScroll($('#fixed').parent()[0]);
		
		var th = $('#fixed > tbody:first-child > tr:first-child th');
	
		upsize(th);
		if($('#fixed').hasClass('double'))
			upsize($('#scrollbar'));
		
		$(window).scroll(function(){
			upsize(th);
			if($('#fixed').hasClass('double'))
				upsize($('#scrollbar'));
		});
		
		function upsize(th){
			if($(window).scrollTop()>$('#fixed').offset().top){
				th.css('top', ($(window).scrollTop()-$('#fixed').offset().top+14)+'px');
			}
			else
				th.css('top',0);
		}
	} */
});