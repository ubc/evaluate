var Evaluate = {
  attach: function() {
    if(evaluate_ajax.use_ajax) {
      jQuery('.eval-link').on('click', Evaluate.attachToLink);
      jQuery('form[name="poll-form"]').on('submit', Evaluate.attachToPoll);
      jQuery('.poll-div a').on('click', Evaluate.attachToPollLink);
    }
  },
  
  attachToLink: function(event) {
    event.preventDefault();
    var args = Evaluate.parseUrl(this.href);
    var element = this;
    jQuery(element).html();
    jQuery.post(evaluate_ajax.ajaxurl, {
      action: 'evaluate-vote',
      data: args
    }, function(data) {
      var newContent = jQuery(data);
      jQuery(element).closest('.evaluate-shell').replaceWith(newContent);
      if(evaluate_ajax.use_ajax) {
        jQuery(newContent).find('.eval-link').on('click', Evaluate.attachToLink);
      }
    })
  },
  
  parseUrl: function(string) {
    var vars = {};
    var parts = string.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m,key,value) {
      vars[key] = value;
    });
    return vars;
  },
  
  attachToPoll: function(event) {
    event.preventDefault();
    var element = this;
    var args = Evaluate.parseUrl('?' + jQuery(element).serialize());
    jQuery.post(evaluate_ajax.ajaxurl, {
      action: 'evaluate-vote',
      data: args
    }, function(data) {
      var newContent = jQuery(data);
      jQuery(element).closest('.evaluate-shell').replaceWith(newContent);
      if(evaluate_ajax.use_ajax) {
        jQuery(newContent).find('form[name="poll-form"]').on('submit', Evaluate.attachToPoll);
      }
    });
  },
  
  attachToPollLink: function(event) {
    event.preventDefault();
    var element = this;
    var args = Evaluate.parseUrl(this.href);
    console.log(args);
    jQuery.post(evaluate_ajax.ajaxurl, {
      action: 'evaluate-vote',
      data: args
    }, function(data) {
      var newContent = jQuery(data);
      jQuery(element).closest('.evaluate-shell').replaceWith(newContent);
      if(evaluate_ajax.use_ajax) {
        jQuery(newContent).find('.poll-div a').on('click', Evaluate.attachToPollLink);
      }
    });
  }
};


/*
 * run on page load
 */
jQuery(window).load(function() {
  Evaluate.attach();
});