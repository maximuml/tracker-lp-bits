<p>{{ __('legacy/offers.text_red_star_required') }}<span class="nx-color-red">*</span>{{ __('legacy/offers.text_red_star_required_end') }}</p>
<div align="center"><form id="compose" action="?new_offer=1" name="compose" method="post">
@csrf
<div class="nx-fgrid nx-fgrid--flat">
<div class="nx-ffull nx-colhead nx-center">{{ __('legacy/offers.text_offers_open_to_all')}}</div>
<div class="nx-fhead"><b>{{ __('legacy/offers.row_type')}}<span class="nx-color-red">*</span></b></div><div class="nx-fcell"> {{ $add_offer['typeOptions'] ?? '' }}</div>
<div class="nx-fhead"><b>{{ __('legacy/offers.row_title')}}<span class="nx-color-red">*</span></b></div><div class="nx-fcell"><input type=text name=name /></div>
<div class="nx-fhead"><b>{{ __('legacy/offers.row_post_or_photo')}}</b></div><div class="nx-fcell"><input type=text name=picture><br />{{ __('legacy/offers.text_link_to_picture') }} <a href="tags.php" title="What is Tag?">{{ __('legacy/offers.text_tag') }}</a> {{ __('legacy/offers.text_link_to_picture_end') }}</div>
<div class="nx-fhead"><b>{{ __('legacy/offers.row_description')}}<b><span class="nx-color-red">*</span></div><div class="nx-fcell">
{{ $add_offer['bbcodeEditor'] ?? '' }}
</div>
<div class="nx-ffull nx-toolbox nx-center"><input id=qr type=submit class=btn value={{ __('legacy/offers.submit_add_offer')}} ></div>
</div></form><br />
