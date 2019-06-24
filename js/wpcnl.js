var menuDateValidator = Marionette.Object.extend( {
    initialize: function() {
        this.listenTo( Backbone.Radio.channel( 'pikaday' ), 'init', this.modifyDatepicker );    
    },

    modifyDatepicker: function( dateObject, fieldModel ) {
         dateObject.pikaday._o.i18n = {
            previousMonth : 'Month Before',
            nextMonth     : 'Month After',
            months        : ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
            weekdays      : ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],
            weekdaysShort : ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']
        };

        var enabled = wpcnl.wpcnl_available_dates;

        if(enabled.length < 1 || enabled == undefined){
          enabled = [];
        } else {
            var start_month = enabled[0].split("-")[1] -1;
            dateObject.pikaday.gotoMonth( start_month );
        }

        dateObject.pikaday._o.disableDayFn = function( date ) {
           
            if ( _.indexOf( enabled, moment( date ).format( "YYYY-MM-DD" ) ) === -1 ) {
                return true;
            }
        }
    }
});

jQuery( document ).ready( function($) {
    new menuDateValidator();
});

jQuery(document).on( 'nfFormReady', function( e, layoutView ) {

    $ = jQuery;

 let whatToObserve = {childList: true, attributes: false, subtree: true, attributeOldValue: false};
  MutationObserver = window.MutationObserver || window.WebKitMutationObserver;

  let mutationObserver = new MutationObserver(function(mutationRecords) {
    let updateDom = false;
    $.each(mutationRecords, function(index, mutationRecord) {
      if (mutationRecord.type === 'childList') {
        if (mutationRecord.addedNodes.length > 0 && mutationRecord.target.localName === 'nf-field') {
          updateDom = true;
        }
      }
    });

    if (updateDom) {
        obs_fields.forEach(function(id, index) { 
            validate_observed(index);
            req_fields.forEach(function(id) { 
                $('#nf-field-' + id).on('change', function(event) {
                    validate_reqired(id);
                });
            });
        });
    }
  });
  mutationObserver.observe(document.body, whatToObserve);

    var rules = wpcnl.wpcnl_data.split('\r\n');
        obs_fields = [];
        req_fields = [];
        err_fields = [];

        rules.forEach(function(el) {
            values = el.split('|');
            req = values[1].split(',');
            obs_fields[values[0]] = req;
            req.forEach(function(i) {
                req_fields.push(i);
            });
            err_fields[values[0]] = values[2] == "-" ? "" : values[2];
        });

        var submit = '#' + jQuery('#nf-form-' + wpcnl.wpcnl_form + '-cont').find('input[type="button"]').attr('id');
        var submit_error = submit.replace('field','error');

        obs_fields.forEach(function(id, index) { 
            $('#nf-field-' + index).on('change', function(event) {
                validate_observed(index);
            });
        });

        req_fields.forEach(function(id) { 
            $('#nf-field-' + id).on('change', function(event) {
                validate_reqired(id);
            });
        });

        jQuery('#nf-form-' + wpcnl.wpcnl_form + '-cont').on('change', function(){

            var set = [];

            obs_fields.forEach(function(id, index) {
                if (0 < $('#nf-field-' + index).prop('checked')) {
                    var disable = true
                    obs_fields[index].forEach(function(i) {
                        if (0 < $('#nf-field-' + i).val()) {
                            disable = false;
                        }
                    });
                    set.push(disable);
                }
            });

            if (set.includes(true)) {
                $(submit).prop('disabled', true);
                $(submit).css('cursor', 'not-allowed');
                $(submit_error).html('<span class="nf-error-msg">' + nfi18n.formErrorsCorrectErrors + '</span>');
            } else {
                $(submit).prop('disabled', false);
                $(submit).css('cursor', 'pointer');
                $(submit_error).html(" ");
            }
        });

        function validate_observed(id) {
            if ('undefined' == typeof id) return;

            if($('#nf-field-' + id). prop("checked") == true) {
                var flag = false;
                
                obs_fields[id].forEach(function(i) {
                    if (0 < $('#nf-field-' + i).val()) {
                        flag = true;
                    }
                });

                if (flag) {
                    obs_fields[id].forEach(function(i) {
                        $('#nf-field-' + i).attr("required", "false");
                    });

                    if (err_fields[id]) {
                        $('#nf-error-' + id).html(" ");
                    }

                } else {
                    obs_fields[id].forEach(function(i) {
                        $('#nf-field-' + i).attr("required", "true");
                    });


                    if (err_fields[id]) {
                        $('#nf-error-' + id).html('<span class="nf-error-msg">' + err_fields[id] + '</span>');
                    }
                }

           } else {
                obs_fields[id].forEach(function(i) {
                    $('#nf-field-' + i).attr("required", "false");
                });

                if (err_fields[id]) {
                    $('#nf-error-' + id).html(" ");
                }
           }
        }

        function validate_reqired(id) {
            obs_fields.forEach(function(el, index) { 
                if (el.includes(id)) {
                    validate_observed(index);
                    return;
                }
            })
        }
});