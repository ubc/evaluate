var Evaluate = {
  attach: function() {
    if(evaluate_ajax.use_ajax) {
      jQuery('.eval-link').live('click', Evaluate.attachToLink);
      jQuery('form[name="poll-form"]').live('submit', Evaluate.attachToPoll);
      jQuery('.poll-div a').live('click', Evaluate.attachToPollLink);
    }
  },
  
  attachToLink: function(event) {
    if(!evaluate_ajax.use_ajax && !evaluate_ajax.stream_active) {
      return; // use url directly
    }
    event.preventDefault();
    var args = Evaluate.parseUrl(this.href);
    var element = this;
    args['_wpnonce'] = jQuery(this).data('nonce');
    jQuery.post(evaluate_ajax.ajaxurl, {
      action: 'evaluate-vote',
      data: args
    }, function(data) {
      if(evaluate_ajax.use_ajax && !evaluate_ajax.stream_active) {
	jQuery(element).closest('.evaluate-shell').replaceWith(data);
      }
    });
  },
  
  parseUrl: function(string) {
    var vars = {};
    var parts = string.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m,key,value) {
      vars[key] = value;
    });
    return vars;
  },
  
  attachToPoll: function(event) {
    if(!evaluate_ajax.use_ajax && !evaluate_ajax.stream_active) {
      return; // use url directly
    }
    event.preventDefault();
    var element = this;
    var args = Evaluate.parseUrl('?' + jQuery(element).serialize());
    jQuery.post(evaluate_ajax.ajaxurl, {
      action: 'evaluate-vote',
      data: args
    }, function(data) {
      if(evaluate_ajax.use_ajax && !evaluate_ajax.stream_active) {
	jQuery(element).closest('.evaluate-shell').replaceWith(data);
      }
    });
  },
  
  attachToPollLink: function(event) {
    event.preventDefault();
    var element = this;
    var args = Evaluate.parseUrl(this.href);
    jQuery.post(evaluate_ajax.ajaxurl, {
      action: 'evaluate-vote',
      data: args
    }, function(data) {
      jQuery(element).closest('div[id^=evaluate-shell-]').html(data);
    });
  }
};

var template = {
  'one-way': doT.template(jQuery('#evaluate-one-way').text()),
  'two-way': doT.template(jQuery('#evaluate-two-way').text()),
  'range': doT.template(jQuery('#evaluate-range').text()),
  'poll-form': doT.template(jQuery('#evaluate-poll-form').text()),
  'poll-results': doT.template(jQuery('#evaluate-poll-results').text())
};

/* page load */
jQuery(window).load(function() {
  Evaluate.attach();
  
  if(typeof CTLT_Stream != "undefined") {
    CTLT_Stream.on('server-push', function (data) {
      //if data received is relevant to evaluate
      if( 'evaluate' == data.type ){
	var element = jQuery('div[id^=evaluate-shell-'+data.data.metric_id+'-'+data.data.content_id+']'); //element to be changed
	//check metric type and make the adjustments needed
	switch(data.data.type) {
	  case 'one-way':
	    data.data.nonce = element.find('.eval-link').data('nonce'); //re-fill nonce field
	    if( data.data.user == evaluate_ajax.user ) {
	      element.data('user-vote', data.data.user_vote);
	    } else {
	      data.data.user_vote = element.data('user-vote');
	      if(data.data.user_vote == 1) {
		data.data.state = '-selected';
	      } else {
		data.data.state = '';
	      }
	    }
	    element.html(template['one-way'](data.data));
	    break;
          
	  case 'two-way':
	    data.data.nonce_up = element.find('.link-up').data('nonce');
	    data.data.nonce_down = element.find('.link-down').data('nonce');
	    if( data.data.user == evaluate_ajax.user ) {
	      element.data('user-vote', data.data.user_vote);
	    } else {
	      data.data.user_vote = element.data('user-vote');
	      if(data.data.user_vote == 1) {
		data.data.state_up = '-selected';
		data.data.state_down = '';
	      } else if(data.data.user_vote == -1) {
		data.data.state_up = '';
		data.data.state_down = '-selected';
	      } else {
		data.data.state_up = '';
		data.data.state_down = '';
	      }
	    }
	    element.html(template['two-way'](data.data));
	    break;
          
	  case 'range':
	    for(var i=1; i<=5; i++) {
	      data.data.nonce[i] = element.find('.link-'+i).data('nonce');
	    }
	    if( data.data.user == evaluate_ajax.user ) {
	      element.data('user-vote', data.data.user_vote);
	    } else {
	      data.data.user_vote = element.data('user-vote');
	      if(data.data.user_vote) {
		data.data.state = '-selected';
		data.data.width = data.data.user_vote / 5.0 * 100;
	      } else {
		data.data.state = '';
		data.data.width = data.data.average / 5.0 * 100;
	      }
	    }
	    element.html(template['range'](data.data));
	    break;
          
	  case 'poll':
	    if( data.data.user == evaluate_ajax.user ) {
	      element.data('user-vote', data.data.user_vote);
	      element.html(template['poll-results'](data.data));
	    } else {
	      data.data._wpnonce = element.find('input[name="_wpnonce"]').val();
	      data.data.user_vote = element.data('user-vote');
	      if(element.find('.poll-results').length > 0) {
		element.html(template['poll-results'](data.data));
	      } else {
		element.html(template['poll-form'](data.data));
	      }
	    }
	    break;
	}
      }
    });
  }
  
});