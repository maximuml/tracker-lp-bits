<x-captcha.row :grid="$grid" :label="$imageLabel"><img src="{{ $imageUrl }}" alt="CAPTCHA" /></x-captcha.row>
<x-captcha.row :grid="$grid" :label="$codeLabel"><input type="text" autocomplete="off" aria-label="{{ $codeLabel }}" class="nx-field__input" name="imagestring" value="" /><input type="hidden" name="imagehash" value="{{ $imagehash }}" /></x-captcha.row>
