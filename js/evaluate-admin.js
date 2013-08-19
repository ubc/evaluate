var Evaluate_Admin = {
	/**
	 * Function to run after the DOM loads in the metric form.
	 */
	onReady: function() {
		// Remove style selection details at the start
		jQuery('input[name="evalu_form[name]"]').on( 'change', Evaluate_Admin.updatePreview );
		jQuery('input[name="evalu_form[display_name]"]').on( 'change', Evaluate_Admin.updatePreview );
		jQuery('input[name="evalu_form[type]"]').on( 'change', Evaluate_Admin.updatePreview );
		jQuery('input[name="evalu_form[style]"]').on( 'change', Evaluate_Admin.updatePreview );
		jQuery('input[name*="evalu_form[one-way]"]').on( 'change', Evaluate_Admin.updatePreview );
		jQuery('input[name*="evalu_form[two-way]"]').on( 'change', Evaluate_Admin.updatePreview );
		jQuery('input[name*="evalu_form[range]"]').on( 'change', Evaluate_Admin.updatePreview );
		jQuery('input[name*="evalu_form[poll]"]').on( 'change', Evaluate_Admin.updatePreview );
		
		// Event handler for type selection
		jQuery('input[name="evalu_form[type]"]').on( 'focus', Evaluate_Admin.updateSelectedType );
		jQuery('input[name="evalu_form[type]"]').on( 'click', Evaluate_Admin.updateSelectedType );
		
		if ( jQuery('input[name="evalu_form[type]"]:checked').val() == undefined ) {
			Evaluate_Admin.updateSelectedType();
		} else {
			var optionsList = jQuery('input[name="evalu_form[type]"]:not(:checked)'); // All metric type menus
			jQuery(optionsList).each( function() {
				jQuery(this).parent().siblings('.context-options').hide();
			} );
		}
		
		Evaluate_Admin.updatePreview();
	},
	
	updatePreview: function() {
		jQuery.post( ajaxurl, jQuery('#metric_form').serialize(), function( response ) {
			jQuery('#metric_preview').html(response);
		} );
	},
	
	/**
	 * Shows/hides further options in the metric form when specific parts are toggled.
	 */
	updateSelectedType: function() {
		var optionsList = jQuery('.type_options > li'); // All metric type menus
		var section = jQuery(this).parent().parent(); // Selected section
		section.find('input[name="evalu_form[style]"]').first().prop('checked', true);
		jQuery(optionsList).children('.context-options').hide(); // Hide everything
		jQuery(section).children().show(); // Show selected section
		
		Evaluate_Admin.updatePreview();
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
		jQuery('input[name="evalu_form[poll][answer]['+num+']"]').on( 'change', Evaluate_Admin.updatePreview )
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
		} else {
			// Do nothing, maybe display a warning?
		}
	},
	
	confirmDeletion: function() {
		return confirm("Are you sure you want to delete this metric?");
	},
};

/**
 * Run on page load
 * jQuery(document).ready() does NOT work here
 */
jQuery(window).load(function() {  
	Evaluate_Admin.onReady();
});