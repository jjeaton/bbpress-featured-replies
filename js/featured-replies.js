jQuery(document).ready(function($){
	$('.featured-replies').click(function(){
		$this = $(this);
		$.post (
			Featured_Replies.ajax_url,
			{
				'action' : 'featured_replies',
				'do': $this.attr('data-do'),
				'reply_id': $this.attr('data-reply_id')
			},
			function ( response ) {
				var action   = $this.attr('data-do'),
					reply_id = $this.attr('data-reply_id'),
					$reply   = $("#post-" + reply_id + ", .post-" + reply_id),
					// Get a jQuery object that has the clicked link, all siblings and the reply divs
					$this_and_reply = $this.siblings('.featured-replies').add($reply).add($this);
				if ( action == 'feature' )
					$this_and_reply.addClass('featured');
				if ( action == 'unfeature' )
					$this_and_reply.removeClass('featured');
				if ( action == 'bury' )
					$this_and_reply.addClass('buried');
				if ( action == 'unbury' )
					$this_and_reply.removeClass('buried');
			}
		);

		return false;
	});

	/* Set classes on Edit Replies (on admin page load) */
	$('.featured-replies.feature').each(function(){
		$this = $(this);
		$tr = $(this).parents('tr');
		if($this.hasClass('featured')) $tr.addClass('featured');
		if($this.hasClass('buried')) $tr.addClass('buried');
	});
});
