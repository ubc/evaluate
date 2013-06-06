var Evaluate = {
    template: {
        'one-way': doT.template( jQuery('#evaluate-one-way').text() ),
        'two-way': doT.template( jQuery('#evaluate-two-way').text() ),
        'range'  : doT.template( jQuery('#evaluate-range').text() ),
        'poll'   : doT.template( jQuery('#evaluate-poll').text() ),
    },
    
    init: function() {
        if ( ! evaluate_ajax.stream_active ) {
            setInterval(Evaluate.reloadMetrics, (evaluate_ajax.frequency * 1000));
        }
    },
    
    parseUrl: function( string ) {
        var vars = {};
        var parts = string.replace( /[?&]+([^=&]+)=([^&]*)/gi, function( m, key, value ) {
            vars[key] = value;
        } );
        
        return vars;
    },
    
    onLinkClick: function( element ) {
        element = jQuery(element);
        
        var args = Evaluate.parseUrl( element.attr('href') );
        args['_wpnonce'] = element.data('nonce');
        
        var data = {
            action: 'evaluate-vote',
            data: args,
        }
        
        jQuery.post( evaluate_ajax.ajaxurl, data, function( response ) {
            if ( ! evaluate_ajax.stream_active ) {
                element.closest('.evaluate-shell').replaceWith(response);
            } //else client will receive the update from socketio
        } );
        
        return false;
    },
    
    reloadMetrics: function() {
        jQuery('.evaluate-shell').each( function() {
            element = jQuery(this);
            
            var data = {
                action: 'evaluate-vote',
                data: {
                    metric_id: element.data('metric-id'),
                    content_id: element.data('content-id'),
                    modified: element.data('modified'),
                    view: true,
                },
            }
            
            jQuery.post( evaluate_ajax.ajaxurl, data, function( response ) {
                if ( response != "false" ) {
                    this.replaceWith( response );
                }
            }.bind( element ) );
        } );
    },
};

/* On page load */
jQuery(window).load(function() {
    Evaluate.init();
    
    if ( typeof CTLT_Stream != "undefined" ) {
        CTLT_Stream.on('server-push', function ( input ) {
            if ( 'evaluate' == input.type ) { // Is the data received relevant to evaluate?
                var elements = jQuery('.evaluate-shell[id^=evaluate-shell-'+input.data.metric_id+'-'+input.data.content_id+']');
                
                jQuery.each( elements, function( index, element ) {
                    var data = input.data;
                    element = jQuery(element);
                    
                    if ( element.data('user') != data.user ) {
                        data.user = element.data('user');
                        data.user_vote = element.data('user-vote');
                        data.width = ( data.user_vote ? data.user_vote : data.average ) / data.length * 100
                    }
                    
                    data.show_user_vote = element.data('show-user-vote') == 1;
                    
                    // Check metric type and make the adjustments needed
                    switch ( data.type ) {
                    case 'one-way':
                        data.nonce = element.find('.eval-link').data('nonce'); //re-fill nonce field
                        
                        if ( data.user == evaluate_ajax.user ) {
                            element.data( 'user-vote', data.user_vote );
                        } else {
                            data.user_vote = element.data('user-vote');
                            
                            if ( data.user_vote == 1 ) {
                                data.state = '-selected';
                            } else {
                                data.state = '';
                            }
                        }
                        break;
                    case 'two-way':
                        data.nonce_up = element.find('.link-up').data('nonce');
                        data.nonce_down = element.find('.link-down').data('nonce');
                        
                        if ( data.user == evaluate_ajax.user ) {
                            element.data('user-vote', data.user_vote);
                        } else {
                            data.user_vote = element.data('user-vote');
                            if ( data.user_vote == 1 ) {
                                data.state_up = '-selected';
                                data.state_down = '';
                            } else if ( data.data.user_vote == -1 ) {
                                data.state_up = '';
                                data.state_down = '-selected';
                            } else {
                                data.state_up = '';
                                data.state_down = '';
                            }
                        }
                        break;
                    case 'range':
                        for ( var i = 1; i <= data.length; i++ ) {
                            data.nonce[i] = element.find('.link-'+i).data('nonce');
                        }
                        
                        if ( data.user == evaluate_ajax.user ) {
                            element.data( 'user-vote', data.user_vote );
                        } else {
                            data.user_vote = element.data('user-vote');
                            if ( data.user_vote ) {
                                data.state = '-selected';
                                data.width = data.user_vote / data.length * 100;
                            } else {
                                data.state = '';
                                data.width = data.average / data.length * 100;
                            }
                        }
                        break;
                    case 'poll':
                        if ( data.user == evaluate_ajax.user ) {
                            element.data( 'user-vote', data.user_vote );
                        } else {
                            data._wpnonce = element.find('input[name="_wpnonce"]').val();
                            data.user_vote = element.data('user-vote');
                        }
                        break;
                    }
                    
                    element.replaceWith( Evaluate.template[data.type]( data ) );
                } );
            }
        } );
    }
} );