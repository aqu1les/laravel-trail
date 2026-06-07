{{-- Placeholder dashboard shell. The compiled front-end (Vue) will be mounted
     here and its assets published to public/vendor/trail via the trail-assets tag.
     The design team owns the actual screens; this is only the mount point. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trail</title>
</head>
<body>
    <div id="trail-app" data-base-url="{{ url(config('trail.path', 'trail')) }}"></div>
</body>
</html>
