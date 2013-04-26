$ = jQuery

$ ->
	$('.postbox#lowtone_woocommerce_coupons_batch').each ->
		$postbox = $ this
		
		$fieldset = $postbox.find '#lowtone_woocommerce_coupons_batch_options'

		disable = (disabled = true) ->
			$fieldset.toggleClass 'disabled', disabled

			$fieldset.find('input').attr 'disabled', disabled

		injected = false

		inject = ->
			return if injected

			$title = $ '#title'

			val = $title.val()

			if '' != val
				val += '-'

			val += '%s'

			$title.val val

			$('label[for="title"]').addClass 'screen-reader-text'

			injected = true

		$postbox.find('input[type="checkbox"]').first().change ->
			checked = $(this).is(':checked')

			disable !checked

			inject() if checked

		disable true

