var Evaluate_Admin = {
	// The two global variables, required to display a preview of the metric before form submission.
	type: new String,
	style: new String,
  
	/**
	 * Function to run after the DOM loads in the metric form.
	 */
	onReady: function() {
		// Remove style selection details at the start
		Evaluate_Admin.showTypeSub();
		
		jQuery('input[name="evalu_form[name]"]').on( 'change', Evaluate_Admin.updatePreview );
		jQuery('input[name="evalu_form[display_name]"]').on( 'change', Evaluate_Admin.updatePreview );
		jQuery('input[name="evalu_form[type]"]').on( 'change', Evaluate_Admin.updatePreview );
		jQuery('input[name="evalu_form[style]"]').on( 'change', Evaluate_Admin.updatePreview );
		jQuery('input[name*="evalu_form[one-way]"]').on( 'change', Evaluate_Admin.updatePreview );
		jQuery('input[name*="evalu_form[two-way]"]').on( 'change', Evaluate_Admin.updatePreview );
		jQuery('input[name*="evalu_form[range]"]').on( 'change', Evaluate_Admin.updatePreview );
		jQuery('input[name*="evalu_form[poll]"]').on( 'change', Evaluate_Admin.updatePreview );
		
		// Event handler for type selection
		jQuery('input[name="evalu_form[type]"]').on( 'focus click', function(event) {
			if ( event.type == 'focus' || event.type == 'click' ) {
				// "this" refers to the DOM element itself
				Evaluate_Admin.type = jQuery(this).val();
				Evaluate_Admin.style = ''; // Reset style in case old entry exists
				Evaluate_Admin.showTypeSub(this);
				Evaluate_Admin.refreshPreview();
			}
		} );
		
		/*
		// Trigger type selection event if something is already selected upon page load
		jQuery('.type_label input:checked').trigger('focus');
	  
		// Event handler for style selection
		jQuery('input[name="evalu_form[style]"]').on('focus click', function(event) {
			if ( event.type == 'focus' || event.type == 'click' ) {
				Evaluate_Admin.style = jQuery(this).val();
				Evaluate_Admin.refreshPreview();
			}
		});
		
		// Trigger style selection event if there is preexisting data
		jQuery('input[name="evalu_form[style]"]:checked').trigger('focus');
	  
		// Event handler for display_name text change
		jQuery('input[name="evalu_form[name]"]').on( 'keyup', Evaluate_Admin.refreshPreview );
		jQuery('input[name="evalu_form[display_name]"]').on( 'focus click', Evaluate_Admin.refreshPreview );
		
		// Event handler for poll preview
		jQuery('input[name*="evalu_form[poll]"]').on( 'keyup', function(element) {
			Evaluate_Admin.previewPoll(element);
		} );
		
		// Trigger change in case the poll data remains from a previous attempt
		jQuery('input[name*="evalu_form[poll]"]').trigger('change');
		
		// Finally refresh poll preview if possible
		Evaluate_Admin.refreshPoll();
		*/
		Evaluate_Admin.updatePreview();
	},
	
	updatePreview: function() {
		jQuery.post( ajaxurl, jQuery('#metric_form').serialize(), function( response ) {
			jQuery('#metric_preview').html(response);
		} );
	},
  
	/**
	 * Adds a new poll question answer in the metric form.
	 */
	addNewAnswer: function() {
		var answers = jQuery('input[name*="evalu_form[poll][answer]"]'); // Fetch all active answer inputs
		var num = answers.length + 1; // Determine answer number
		var last_field = answers.splice( -1, 1 ); // Get last element
		
		// Insert new answer field after the current last one
		// Since we select by input name, the parent i.e. <label> will be the element which we add the last field after.
		jQuery(last_field).parent().after('<label>Answer '+num+': <input type="text" name="evalu_form[poll][answer]['+num+']" class="regular-text" /></label>');
		
		// Add another answer field to the preview
		jQuery('.poll-list').append('<li class="poll-answer"><label><input type="radio" name="poll-preview" /></label></li>');
		
		// Lastly add an event handler on change for preview purposes
		jQuery('input[name="evalu_form[poll][answer]['+num+']"]').on( 'change', function(element) {
			Evaluate_Admin.previewPoll(element);
		} )
	},
  
	/**
	 * Removes the last inserted question answer field from the metric form
	 * makes sure at least 2 answers remain at all times.
	 */
	removeLastAnswer: function() {
		var answers = jQuery('input[name*="evalu_form[poll][answer]"]');
		var num = answers.length;
		
		if ( num >= 3 ) {
			var removed = answers.splice( -1, 1 );
			var index = jQuery(removed).parent().parent().index() - 2;
			var answer = jQuery('.poll-answer').get(index);
			jQuery(answer).remove(); // Remove answer from preview
			jQuery(removed).parent().remove(); // Remove the parent-parent (grandparent? lol) of the input field
			jQuery(removed).parent().css('border', '1px solid red');
		} else {
			// Do nothing, maybe display a warning?
		}
	},
	
	/**
	 * Shows/hides further options in the metric form when specific parts are toggled.
	 */
	showTypeSub: function( element ) {
		var optionsList = jQuery('.type_options > li'); // All metric type menus
		var section = jQuery(element).parent().parent(); // Selected section
		jQuery(optionsList).children().not('.type_label').hide(); // Hide everything
		//jQuery('input:hidden"').removeAttr('checked'); // Deselect hidden style choice
		jQuery(section).children().show(); // Show selected section
	},
	
	/**
	 * Refreshes the components required to display a preview before submitting the form.
	 */
	refreshPreview: function( element ) {
		jQuery('div[id*="prev_"]').hide();
		if ( Evaluate_Admin.type != '' ) { // Prevent premature refresh if no type is selected
			jQuery('#prev_'+Evaluate_Admin.type+'_'+Evaluate_Admin.style).show();
		}
		
		jQuery('#preview_name').html( jQuery('input[name="evalu_form[name]"]').val() );
		jQuery('#preview_name').toggle( jQuery('input[name="evalu_form[display_name]"]').is(':checked') );
	},
	
	/**
	 * Updates the poll fields in the preview as the real fields get updated.
	 */
	previewPoll: function( element ) {
		element = jQuery(element.target);
		
		if ( element.attr('name') == 'evalu_form[poll][question]' ) {
			jQuery('.poll-question').html(element.val());
		} else {
			// The index is actually -2, because jQuery uses 0-indexes, and the answers are within individual divs,
			// with a leading element for the question
			var index = element.parent().index() - 3;
			var answer = jQuery('.poll-answer').get(index);
			jQuery(answer).html('<label><input type="radio" name="poll-preview" /> '+element.val()+'</label>');
			console.log(index);
		}
	},
	
	/**
	 * Refreshes all elements in the poll preview display to sync with input fields
	 * should only be needed when user wants to edit metric, upon first load to load poll answers correctly.
	 */
	refreshPoll: function() {
		jQuery('.poll-question').html( jQuery('input[name="evalu_form[poll][question]"]').val() ); //sync question
		var answer_fields = jQuery('input[name*="evalu_form[poll][answer]"]');
		
		for ( var i = 1; i <= answer_fields.length; i++ ) { // Loop through text answer fields and change answers
			if ( ! jQuery('.poll-answer').get(i-1) ) { // -1 because inputs start from 1, index starts from 0
				// Add another answer field to the preview
				jQuery('.poll-list').append('<li class="poll-answer"><label><input type="radio" name="poll-preview" /></label></li>');
				jQuery('input[name*="evalu_form[poll][answer]['+i+']"]').trigger('keyup'); // Trigger so previewPoll runs to change the preview to match the field
			}
		}
	}
};

/**
 * Run on page load
 * jQuery(document).ready() does NOT work here
 */
jQuery(window).load(function() {  
	Evaluate_Admin.onReady();
	
	// Disable metric links if on admin panel
	links = jQuery('.metric-preview a');
	if ( links.length > 0 ) {
		jQuery.each( links, function( index, value ) { jQuery(value).attr( 'href', 'javascript:void(0)' ); } );
	}
});