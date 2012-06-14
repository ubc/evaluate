var Evaluate_Add = {
	onReady : function() {
		
		jQuery('input.evaluate-type-selection').on('focus click', function( event ) {
			if( event.type == 'focus' || event.type == 'click' ) Evaluate_Add.show_selection( this );
			
		});
		jQuery('#preview').show();
		
	},
	
	show_selection: function( el ) {
		var parent = jQuery(el).parent().parent();
		parent.parent().find('.evaluate-type-shell').hide();
		parent.find('.evaluate-type-shell').show();
	
	},
	
	
	
	
	

}

jQuery('document').ready( Evaluate_Add.onReady );