<?xml version="1.0" encoding="UTF-8"?>
{{-- Meta Commerce Manager product feed. Built by MetaFeedController, which
     documents the id scheme this file renders. Values are escaped by Blade, so
     an ampersand or quote in a product name cannot break the XML. --}}
<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
<channel>
<title>{{ get_setting('site_name') ?: config('app.name') }}</title>
<link>{{ rtrim(config('app.frontend_url'), '/') }}</link>
<description>{{ get_setting('meta_description') ?: (get_setting('site_name') . ' product feed') }}</description>
@foreach($items as $item)
<item>
<g:id>{{ $item['id'] }}</g:id>
<g:item_group_id>{{ $item['item_group_id'] }}</g:item_group_id>
<g:title>{{ $item['title'] }}</g:title>
<g:description>{{ $item['description'] }}</g:description>
<g:link>{{ $item['link'] }}</g:link>
<g:image_link>{{ $item['image_link'] }}</g:image_link>
<g:price>{{ $item['price'] }}</g:price>
<g:availability>{{ $item['availability'] }}</g:availability>
<g:condition>{{ $item['condition'] }}</g:condition>
<g:brand>{{ $item['brand'] }}</g:brand>
</item>
@endforeach
</channel>
</rss>
