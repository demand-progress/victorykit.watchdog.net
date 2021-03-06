function toggle_visibility(id) {
    var e = document.getElementById(id);
    if(e.style.display == 'block')
        e.style.display = 'none';
    else
        e.style.display = 'block';
}

/* idTabs ~ Sean Catchpole - Version 2.2 - MIT/GPL */
(function(){var dep={"jQuery":"http://code.jquery.com/jquery-latest.min.js"};var init=function(){(function($){$.fn.idTabs=function(){var s={};for(var i=0;i<arguments.length;++i){var a=arguments[i];switch(a.constructor){case Object:$.extend(s,a);break;case Boolean:s.change=a;break;case Number:s.start=a;break;case Function:s.click=a;break;case String:if(a.charAt(0)=='.')s.selected=a;else if(a.charAt(0)=='!')s.event=a;else s.start=a;break;}}
if(typeof s['return']=="function")
s.change=s['return'];return this.each(function(){$.idTabs(this,s);});}
$.idTabs=function(tabs,options){var meta=($.metadata)?$(tabs).metadata():{};var s=$.extend({},$.idTabs.settings,meta,options);if(s.selected.charAt(0)=='.')s.selected=s.selected.substr(1);if(s.event.charAt(0)=='!')s.event=s.event.substr(1);if(s.start==null)s.start=-1;var showId=function(){if($(this).is('.'+s.selected))
return s.change;var id="#"+this.href.split('#')[1];var aList=[];var idList=[];$("a",tabs).each(function(){if(this.href.match(/#/)){aList.push(this);idList.push("#"+this.href.split('#')[1]);}});if(s.click&&!s.click.apply(this,[id,idList,tabs,s]))return s.change;for(i in aList)$(aList[i]).removeClass(s.selected);for(i in idList)$(idList[i]).hide();$(this).addClass(s.selected);$(id).show();return s.change;}
var list=$("a[href*='#']",tabs).unbind(s.event,showId).bind(s.event,showId);list.each(function(){$("#"+this.href.split('#')[1]).hide();});var test=false;if((test=list.filter('.'+s.selected)).length);else if(typeof s.start=="number"&&(test=list.eq(s.start)).length);else if(typeof s.start=="string"&&(test=list.filter("[href*='#"+s.start+"']")).length);if(test){test.removeClass(s.selected);test.trigger(s.event);}
return s;}
$.idTabs.settings={start:0,change:false,click:null,selected:".selected",event:"!click"};$.idTabs.version="2.2";$(function(){$(".idTabs").idTabs();});})(jQuery);}
var check=function(o,s){s=s.split('.');while(o&&s.length)o=o[s.shift()];return o;}
var head=document.getElementsByTagName("head")[0];var add=function(url){var s=document.createElement("script");s.type="text/javascript";s.src=url;head.appendChild(s);}
var s=document.getElementsByTagName('script');var src=s[s.length-1].src;var ok=true;for(d in dep){if(check(this,d))continue;ok=false;add(dep[d]);}if(ok)return init();add(src);})();



jQuery('document').ready( function ($) {

    $("#wpstagecoach-import-tabs ul").idTabs(); 

        // Select all
    jQuery("A[href='#select_all']").click( function() {
        jQuery("#" + jQuery(this).attr('rel') + " INPUT[type='checkbox']").attr('checked', true);
        return false;
    });

    // Select none
    jQuery("A[href='#select_none']").click( function() {
        jQuery("#" + jQuery(this).attr('rel') + " INPUT[type='checkbox']").attr('checked', false);
        return false;
    });

    // Invert selection
    jQuery("A[href='#invert_selection']").click( function() {
        jQuery("#" + jQuery(this).attr('rel') + " INPUT[type='checkbox']").each( function() {
            jQuery(this).attr('checked', !jQuery(this).attr('checked'));
        });
        return false;
    });

    $("#checkAll").click(function () {
        if ($("#checkAll").is(':checked')) {
            $("input.overview").each(function () {
                $(this).prop("checked", true);
            });

        } else {
            $("input.overview").each(function () {
                $(this).prop("checked", false);
            });
        }
    });

    $("#checkFiles").click(function () {
        if ($("#checkFiles").is(':checked')) {
            $("input.file").each(function () {
                $(this).prop("checked", true);
            });

        } else {
            $("input.file").each(function () {
                $(this).prop("checked", false);
            });
        }
    });   

    $("#checkTables").click(function () {
        if ($("#checkTables").is(':checked')) {
            $("input.table").each(function () {
                $(this).prop("checked", true);
            });

        } else {
            $("input.table").each(function () {
                $(this).prop("checked", false);
            });
        }
    });  
/*
    $(document).ready(function() {
    $("body").prepend('<div id="overlay" class="ui-widget-overlay" style="display: none;"></div>');
    $("body").prepend("<div id='PleaseWait' style='display: none;'><img src='../wp-content/plugins/wpstagecoach/assets/wpsc-ajax-loader.gif'/></div>");
    });
*/

    // make an easy to use show/hide function.
    jQuery('.toggle').click(function(){
          jQuery('.more').toggle('slow');
    });


    $('#wpsc').submit(function() {
        var pass = true;
        //some validations

        if(pass == false){
            return false;
        }
        $("#overlay, #PleaseWait").show();

        return true;
    });

     $('#caching').change(function() {
        $('.caching').toggle(this.checked);
    });

     $('#password').change(function() {
        $('.password').toggle(this.checked);
    });

     $('#hotlink').change(function() {
        $('.hotlink').toggle(this.checked);
    });

     // feedback form AJAX - show/hide comment label
    $("#no").click(function () {
            if ($("#no").is(':checked')) {
                $("#comment_no").show();
                $("#comment_yes").hide();
            } 
    });
        $("#yes").click(function () {
            if ($("#yes").is(':checked')) {
                $("#comment_yes").show();
                $("#comment_no").hide();
            } 
        });

     // feedback form AJAX
    $('#submit-feedback').on('click', function (e) {  // this is what triggers the function
        $(".wpstagecoach-feedback").addClass('waiting');

        var url = wpstagecoachajax.ajaxurl; // this tells the function to be ajaxy
        
        var data = { // gather all the variables to send them to PHP
            'action': 'wpsc_submit_feedback', // this is the name of the PHP function
            'information': $("#wpsc-happiness-form").serialize(),
        }

        if (($('#comments').val() == "") && ($("input[name='worked']:checked").val() == "no") ){ // check if worked is no and comment form is blank
            $(".wpstagecoach-feedback").removeClass('waiting');
            $('#comments').focus(); // comment textarea focus
            $('.comments-error').show(); //show error message
           return false;
        } else {
        $.post(url, data, function (response) { // this is what JS gets back from PHP
           $("#feedback-result").html(response); // this will put a value in an HTML element
                $(".wpstagecoach-feedback").removeClass('waiting');
                $("#feedback-result").addClass('result');
                $("#wpsc-happiness-form").hide('slow');
                $(".wpstagecoach-feedback-message").hide('slow');
            });
        }
    });

    // manual import form AJAX
    $('#submit-manual-import').on('click', function (e) {  // this is what triggers the function
        $('#wpsc-manual-files').addClass('wpstagecoach-waiting');
        $('#submit-manual-import').hide();
        var url = wpstagecoachajax.ajaxurl; // this tells the function to be ajaxy
        var nonce = $('#wpsc-manual-import-nonce').val();
        var user = $('#wpsc-user').val();
        var apikey = $('#wpsc-key').val();
        var live_site = $('#wpsc-live-site').val();
        var stage_site = $('#wpsc-stage-site').val();

        var data = { // gather all the variables to send them to PHP
            'action': 'wpsc_manual_import_ajax', // this is the name of the PHP function
            'nonce': nonce,
            'user': user,
            'apikey': apikey,
            'live-site': live_site,
            'stage-site': stage_site
        }

        $.post(url, data, function (response) { // this is what JS gets back from PHP
            $('#wpsc-manual-files').removeClass('wpstagecoach-waiting');
           $("#wpsc-manual-files").html(response); // this will put a value in an HTML element
        });
    });

    // update nonce fields
    $('.wpstagecoach-update-step-nonce').on('click', function (e) {  // this is what triggers the function
        e.preventDefault();

        var name = $( this ).attr( 'name' );
        var value = $( this ).val();

        if( 'wpsc-everythings-peachy-delete' == name ) {
            var x = confirm("Whoa there!  Are you sure you want to delete this staging site?");
              if (!x)
                return false;
        }
        
        var url = wpstagecoachajax.ajaxurl; // this tells the function to be ajaxy
        var type = $('input[name="wpsc-type"]').val();
        var step = $('input[name="wpsc-step"]').val();
        
        var data = { // gather all the variables to send them to PHP
            'action': 'wpstagecoach_update_step_nonce', // this is the name of the PHP function
            'type': type,
            'step': step
        };

        $.post(url, data, function (response) { // this is what JS gets back from PHP
           $('[name="wpsc-nonce"]').val(response); // this will put a value in an HTML element
            var input = document.createElement('input'); // pass the value of the submit button
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            $('.wpstagecoach-update-step-nonce-form').append(input);
           $('.wpstagecoach-update-step-nonce-form').submit();
        });
    });
 
    $("#wpsc-advanced-create-mysql-rows-per-step").click(function () {
        if ($("#wpsc-advanced-create-mysql-rows-per-step").is(':checked')) {
          $("#wpstagecoach-advanced-create-mysql-rows-per-step-form").show();
        } else {
            $("#wpstagecoach-advanced-create-mysql-rows-per-step-form").hide();
        }
    });
    
    $("#wpsc-advanced-create-mysql-custom-iterations").click(function () {
        if ($("#wpsc-advanced-create-mysql-custom-iterations").is(':checked')) {
          $("#wpstagecoach-advanced-create-mysql-custom-iterations-form").show();
        } else {
            $("#wpstagecoach-advanced-create-mysql-custom-iterations-form").hide();
        }
    });

    $("#wpsc-advanced-create-skip-directories").click(function () {
        if ($("#wpsc-advanced-create-skip-directories").is(':checked')) {
          $("#wpstagecoach-advanced-create-skip-directories-form").show();
        } else {
            $("#wpstagecoach-advanced-create-skip-directories-form").hide();
        }
    });

    $("#wpsc-advanced-create-mysql-bypass-tables").click(function () {
        if ($("#wpsc-advanced-create-mysql-bypass-tables").is(':checked')) {
          $("#wpstagecoach-advanced-create-mysql-bypass-tables-form").show();
        } else {
            $("#wpstagecoach-advanced-create-mysql-bypass-tables-form").hide();
        }
    });







    var step = $( '#wpsc-import-step' ).val();
    if( typeof step  !== 'undefined' ){
        if( 6 == step ){
            // do_file_import();
            // do_db_import();
            do_ajax_import();
        }
    }


    function do_ajax_import() {
        var url = wpstagecoachajax.ajaxurl; 
        var nonce = $('#wpsc-do-import-nonce').val();
        var data = {
            'action': 'wpstagecoach_do_ajax_import',
            'nonce': nonce,
        };


// console.log( '_-_-_-_-_-_ doing import _-_-_-_-_-_' );
// console.log( '_-_-_-_-_-_  _-_-_-_-_-_' );


        $.post( url, data, function ( response ) {
console.log('response: ');
console.log(response);
            try{
                responseobj = JSON.parse( response );
            } catch( e ) {
                $('#database_import_results').append( '<div class="wpstagecoach-error">'+response+'</div>' );
                do_ajax_import();
                return;
            }

        // responseobj = JSON.parse( response );

console.log(responseobj);
console.log('/response');

console.log( '_-_-_-_-_-_ doing '+responseobj.type+' import _-_-_-_-_-_' );





/*
thoughts...
rather than leaving
file:
Imported 32 files. 0 file remaining.
database:
Imported 91 rows. 0 rows remaining.


It would be nice to write a hidden div/p in the import file that JS un-hides when it is "done" with a particular funciton




*/



console.log( '_-_-_-_-_-_ '+responseobj.type+' import is "'+responseobj.status+'" _-_-_-_-_-_' );
// console.log( 'log TESTTESTTESTTEST log' );
// $('#database_import_results').html('html TESTTESTTESTTEST html' );

            if( responseobj ) {
                if( 'error' == responseobj.status ) {
// console.log( 'log TESTTESTTESTTEST log' );
                    if( typeof responseobj.type  === 'undefined' ){
                        responseobj.type = 'file_import_results, #database';
                    }

                    $('#'+responseobj.type+'_import_results').append('<div class="wpstagecoach-error">We encountered an error. Please contact support with the following message:<br />' + responseobj.message + '</div>' );
                // } else if( responseobj.error == true ){
// console.log( '_-_-_-_-_-_ what the heck? _-_-_-_-_-_' );
// console.log(responseobj);

// $("#"+responseobj.'database'+"_import_results").html(total);

                    $('#database_import_results').append('<div class="wpstagecoach-error">We encountered an error. Please contact support with the following message:<br />' + responseobj.message + '</div>' );
                    // $("#database_import_results").append('We didn\'t get a valid response. Please contact support');
                   throw new Error();

                } else if( 'finished' == responseobj.status ) {
                    var success = 'Finshed importing '+responseobj.type;
                    $("#"+responseobj.type+"_import_results").html(success);


                } else {
                    $("#working-on").html(responseobj.last);
                    $('#database_import_results').append('<div class="wpstagecoach-error">We encountered an error. Please contact support with the following message:<br />' + responseobj.message + '</div>' );

                    // console.log('continue');
                    if( responseobj.type == 'database' ){
                        if( responseobj.rowsleft == 0 ){
                            var total = 'Finished importing the database.';
                        } else {
                            var total = 'Imported ' + responseobj.count + ' rows. ' + responseobj.rowsleft + ' rows remaining.';
                        }
                    } else {
                        if( responseobj.rowsleft == 0 ){
                            var total = 'Finished importing the files.';
                        } else {
                            var total = 'Imported ' + responseobj.count + ' files. ' + responseobj.rowsleft + ' file remaining.';
                        }
                    }

                    // var total = 'Updated ' + responseobj.count + ' of ' + $('#working-on').val() + ' database rows';
                    $("#"+responseobj.type+"_import_results").html(total);
                    do_ajax_import();
                }
            } else {
                $("#"+responseobj.type+"_import_results").html('We didn\'t get a valid response. Please contact support');
                throw new Error();
            } // end of if responseobj

        });
    } // end of function do_import











    /*
    
    function do_db_import() {
        var url = wpstagecoachajax.ajaxurl; // this tells the function to be ajaxy
        var nonce = $('#wpsc-do-import-nonce').val();
        var data = {
            'action': 'wpstagecoach_do_db_import',
            'nonce': nonce,
        };


console.log( '_-_-_-_-_-_ doing DB import _-_-_-_-_-_' );


        $.post( url, data, function ( response ) {

console.log('response: ');
// console.log(response);
responseobj = JSON.parse( response );

console.log(responseobj);
console.log('/response');

console.log( '_-_-_-_-_-_ doing DB import _-_-_-_-_-_' );


            if( responseobj ) {
                if( 'error' == responseobj.status ) {
                    $("#import_results").html('<div class="wpstagecoach-error">We encountered an error. Please contact support with the following message:<br />' + responseobj.message + '</div>' );
                } else if( 'finished' == responseobj.status ) {
                    var success = 'All done';
                    $("#import_results").html(success);
                } else {
$("#working-on").html(responseobj.last);

                    console.log('continue');
                    var total = 'Imported ' + responseobj.count + ' rows. ' + responseobj.rowsleft + ' rows remaining.';
                    // var total = 'Updated ' + responseobj.count + ' of ' + $('#working-on').val() + ' database rows';
                    $("#import_results").html(total);
// throw new Error('omgbbq');
                    do_db_import();
                }
            } else {
                $("#import_results").html('We didn\'t get a valid response. Please contact support');
console.log('omgwtfbbq.');
throw new Error();


            } // end of if responseobj

        });
    }   




    
    function do_file_import() {
        var url = wpstagecoachajax.ajaxurl; // this tells the function to be ajaxy
        var nonce = $('#wpsc-do-import-nonce').val();
        var data = {
            'action': 'wpstagecoach_do_file_import',
            'nonce': nonce,
        };
console.log( '_-_-_-_-_-_ doing File import _-_-_-_-_-_' );

        $.post( url, data, function ( response ) {

console.log('response: ');
// console.log(response);
responseobj = JSON.parse( response );

console.log(responseobj);
console.log('/response');



            if( responseobj ) {
                if( 'error' == responseobj.status ) {
                    $("#import_results").html('<div class="wpstagecoach-error">We encountered an error. Please contact support with the following message:<br />' + responseobj.message + '</div>' );
                } else if( 'finished' == responseobj.status ) {
                    var success = 'All done';
                    $("#import_results").html(success);
                } else {
$("#working-on").html(responseobj.last);

                    console.log('continue');
                    var total = 'Imported ' + responseobj.count + ' rows. ' + responseobj.rowsleft + ' rows remaining.';
                    // var total = 'Updated ' + responseobj.count + ' of ' + $('#working-on').val() + ' database rows';
                    $("#import_results").html(total);
// throw new Error('omgbbq');
                    do_file_import();
                }
            } else {
                $("#import_results").html('We didn\'t get a valid response. Please contact support');
console.log('omgwtfbbq.');
throw new Error();


            } // end of if responseobj

        });
    }   


*/












});