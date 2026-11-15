<rss version="2.0"><channel>
        <title>{{ $vm->channelTitle }}</title>
        <link><![CDATA[{{ $vm->baseUrl }}]]></link>
        <description><![CDATA[{{ $vm->description }}]]></description>
        <language>zh-cn</language>
        <copyright>{{ $vm->copyright }}</copyright>
        <managingEditor>{{ $vm->siteEmail }} ({{ $vm->siteName }} Admin)</managingEditor>
        <webMaster>{{ $vm->siteEmail }} ({{ $vm->siteName }} Webmaster)</webMaster>
        <pubDate>{{ $vm->pubDate }}</pubDate>
        <generator>{{ $vm->projectName }} RSS Generator</generator>
        <docs><![CDATA[http://www.rssboard.org/rss-specification]]></docs>
        <ttl>60</ttl>
        <image>
            <url><![CDATA[{{ $vm->baseUrl }}/pic/rss_logo.jpg]]></url>
            <title>{{ $vm->channelTitle }}</title>
            <link><![CDATA[{{ $vm->baseUrl }}]]></link>
            <width>100</width>
            <height>100</height>
            <description>{{ $vm->channelTitle }}</description>
        </image>
@foreach ($vm->items as $item)
<item>
            <title><![CDATA[{{ $item->title }}]]></title>
            <link>{{ $item->url }}</link>
            <description><![CDATA[{{ $item->content }}]]></description>
            <author>{{ $item->author }}&#64;{{ $vm->httpHost }} ({{ $item->author }})</author>
            <category domain="{{ $vm->baseUrl }}/web/torrents?cat={{ $item->categoryId }}">{{ $item->categoryName }}</category>
            <comments><![CDATA[{{ $item->commentsUrl }}]]></comments>
            <enclosure url="{{ $item->downloadUrl }}" length="{{ $item->size }}" type="application/x-bittorrent" />
            <guid isPermaLink="false">{{ $item->guid }}</guid>
            <pubDate>{{ $item->pubDate }}</pubDate>
        </item>
@endforeach
</channel>
</rss>
