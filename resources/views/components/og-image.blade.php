<meta property="og:image" content="{{ $url }}">
<meta property="og:image:width" content="{{ $width }}">
<meta property="og:image:height" content="{{ $height }}">
@if($alt)
<meta property="og:image:alt" content="{{ $alt }}">
@endif
@if($twitter)
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="{{ $url }}">
@endif
