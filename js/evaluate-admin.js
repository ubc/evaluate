var Evaluate_Admin = {
  ////the two global variables, required to display
  //a preview of the metric before form submission
  type : new String,
  style : new String,

  /*
   *adds a new poll question answer in the metric form
   */
  addNewAnswer : function() {
    var answers = jQuery('input[name*="evalu_form[poll][answer]"]'); //fetch all active answer inputs
    var num = answers.length + 1; //determine answer number
    var last_field = answers.splice(-1,1); // get last element
    //insert new answer field after the current last one
    //since we select by input name, the parent of parent i.e. <div class="indent"> will be the element
    //which we add the last field after.
    jQuery(last_field).parent().parent().after(
      '<div class="indent"><label>Answer ' + num + ': <input type="text" name="evalu_form[poll][answer][' + num + ']" class="regular-text" /></label></div>'
      );
        
    //add another answer field to the preview
    jQuery('.poll-list').append('<li class="poll-answer"><label><input type="radio" name="poll-preview" /></label></li>');
    
    //lastly add an event handler on change for preview purposes
    jQuery('input[name="evalu_form[poll][answer][' + num + ']"]').on('change', function(element) {
      Evaluate_Admin.previewPoll(element);
    })
  },

  /*
   *removes the last inserted question answer field from the metric form
   *makes sure at least 2 answers remain at all times
   */
  removeLastAnswer : function() {
    var answers = jQuery('input[name*="evalu_form[poll][answer]"]');
    var num = answers.length;
    if(num < 3) {
    //do nothing, maybe display a warning?
    } else {
      var removed = answers.splice(-1,1);
      var index = jQuery(removed).parent().parent().index() - 2;
      var answer = jQuery('.poll-answer').get(index);
      jQuery(answer).remove(); //remove answer from preview
      jQuery(removed).parent().parent().remove(); //remove the parent-parent (grandparent? lol) of the input field
    }
  },

  /*
   *function to run after the DOM loads in the metric form
   */
  addFormReady : function() {
    //remove style selection details at the start
    Evaluate_Admin.showTypeSub();
    //event handler for type selection
    jQuery('input[name="evalu_form[type]"]').on('focus click', function(event) {
      if(event.type == 'focus' || event.type == 'click') {
        //"this" refers to the DOM element itself
        Evaluate_Admin.type = jQuery(this).val();
        Evaluate_Admin.style = ''; //reset style in case old entry exists
        Evaluate_Admin.showTypeSub(this);
        Evaluate_Admin.refreshPreview();
      }
    });
    
    //trigger type selection event if something is already selected upon page load
    jQuery('.type_label input:checked').trigger('focus');
  
    //event handler for style selection
    jQuery('input[name="evalu_form[style]"]').on('focus click', function(event) {
      if(event.type == 'focus' || event.type == 'click') {
        Evaluate_Admin.style = jQuery(this).val();
        Evaluate_Admin.refreshPreview();
      }
    });
    
    //trigger style selection event if there is preexisting data
    jQuery('input[name="evalu_form[style]"]:checked').trigger('focus');
  
    //event handler for display_name text change
    jQuery('input[name="evalu_form[name]"]').on('change', Evaluate_Admin.refreshPreview);
    jQuery('input[name="evalu_form[display_name]"]').on('focus click', Evaluate_Admin.refreshPreview);
    
    //event handler for poll preview
    jQuery('input[name*="evalu_form[poll]"]').on('change', function(element) { 
      Evaluate_Admin.previewPoll(element);
    });
    
    //trigger change in case the poll data remains from a previous attempt
    jQuery('input[name*="evalu_form[poll]"]').trigger('change');
    
    //finally refresh poll preview if possible
    Evaluate_Admin.refreshPoll();
  },

  /*
   *shows/hides further options in the metric form when specific parts are toggled
   */
  showTypeSub : function(element) {
    var optionsList = jQuery('.type_options > li'); //all metric type menus
    var section = jQuery(element).parent().parent(); //selected section
    jQuery(optionsList).children().not('.type_label').hide(); //hide everything
    //jQuery('input:hidden"').removeAttr('checked'); //deselect hidden style choice
    jQuery(section).children().show(); //show selected section
  },

  /*
   *refreshes the compoenents required to display a preview
   *before submitting the form
   */
  refreshPreview : function(element) {
    jQuery('div[id*="prev_"]').hide();
    if(Evaluate_Admin.type != '') { //prevent premature refresh if no type is selected
      jQuery('div[id*="prev_'+Evaluate_Admin.type+'_'+Evaluate_Admin.style+'"]').show();
    }
    jQuery('#preview_name').html(jQuery('input[name="evalu_form[name]"]').val());
    jQuery('#preview_name').toggle(jQuery('input[name="evalu_form[display_name]"]').is(':checked'));
  },
  
  /*
   *updates the poll fields in the preview as the real fields get updated
   */
  previewPoll : function(element) {
    var element = jQuery(element.target);
    if(element.attr('name') == 'evalu_form[poll][question]') {
      jQuery('.poll-question').html(element.val());
    } else {
      /* the index is actually -2, because jQuery uses 0-indexes, and the answers are within individual divs,
       * with a leading element for the question */
      var index = element.parent().parent().index() - 2;
      var answer = jQuery('.poll-answer').get(index);
      jQuery(answer).html('<label><input type="radio" name="poll-preview" />' + element.val() + '</label>');
    }
  },
  
  /*
   *refreshes all elements in the poll preview display to sync with input fields
   *should only be needed when user wants to edit metric, upon first load to load poll answers correctly
   */
  refreshPoll : function() {
    jQuery('.poll-question').html(jQuery('input[name="evalu_form[poll][question]"]').val()); //sync question
    var answer_fields = jQuery('input[name*="evalu_form[poll][answer]"]');
    for(var i=1; i<=answer_fields.length; i++) { //loop through text answer fields and change answers
      if(jQuery('.poll-answer').get(i-1)) { //-1 because inputs start from 1, index starts from 0
      } else {
        //add another answer field to the preview
        jQuery('.poll-list').append('<li class="poll-answer"><label><input type="radio" name="poll-preview" /></label></li>');
        jQuery('input[name*="evalu_form[poll][answer]['+i+']"]').trigger('change'); //trigger so previewPoll runs to change the preview to match the field
      }
    }
  }
};

/*
 * run on page load
 * jQuery(document).ready() does NOT work here
 */
jQuery(window).load(function() {  
  if(jQuery('input[name="evalu_form[type]"]').length) { //length > 0 i.e element exists
    Evaluate_Admin.addFormReady();
  }
  //disable metric links if on admin panel
  links = jQuery('.evaluate-shell a');
  forms = jQuery('.evaluate-shell form');
  if(links.length > 0) {
    jQuery.each(links, function(index, value){jQuery(value).attr('href', 'javascript:void(0)')});
  }
  if(forms.length > 0) {
    jQuery.each(forms, function(index, value){jQuery(value).live('submit', function(event){event.preventDefault();})});
  }
});