jQuery(document).ready(function() {
    jQuery('#start_date').datepicker({
        dateFormat : 'dd-mm-yy',
		changeMonth: true,
      	changeYear: true,
		yearRange: "2019:2039",
        onSelect: function (date) {
            var date2 = $('#start_date').datepicker('getDate');
            date2.setDate(date2.getDate() + 1);
            $('#end_date').datepicker('setDate', date2);
            //sets minDate to dt1 date + 1
            $('#end_date').datepicker('option', 'minDate', date2);
        }
    });

    jQuery('#end_date').datepicker({
        dateFormat : 'dd-mm-yy',
		changeMonth: true,
      	changeYear: true,
		yearRange: "2019:2039",
        onClose: function () {
            var dt1 = $('#start_date').datepicker('getDate');
            var dt2 = $('#end_date').datepicker('getDate');
            //check to prevent a user from entering a date below date of dt1
            if (dt2 <= dt1) {
                var minDate = $('#end_date').datepicker('option', 'minDate');
                $('#end_date').datepicker('setDate', minDate);
            }
        }
    });

    jQuery('#start_date').on('change', function(){
    	var date = $(this).val();
    	jQuery('#end_date').datetimepicker({minDate: date});  
	});
});