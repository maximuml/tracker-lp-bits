<rss version="2.0"><channel>
        <title>{{ $channelTitle }}</title>
        <link><![CDATA[{{ $baseUrl }}]]></link>
        <description><![CDATA[{{ $description }}]]></description>
        <language>zh-cn</language>
        <copyright>{{ $copyright }}</copyright>
        <managingEditor>{{ $siteEmail }} ({{ $siteName }} Admin)</managingEditor>
        <webMaster>{{ $siteEmail }} ({{ $siteName }} Webmaster)</webMaster>
        <pubDate>{{ $pubDate }}</pubDate>
        <generator>{{ $projectName }} RSS Generator</generator>
        <docs><![CDATA[http://www.rssboard.org/rss-specification]]></docs>
        <ttl>60</ttl>
        <image>
            <url><![CDATA[{{ $baseUrl }}/pic/rss_logo.jpg]]></url>
            <title>{{ $channelTitle }}</title>
            <link><![CDATA[{{ $baseUrl }}]]></link>
            <width>100</width>
            <height>100</height>
            <description>{{ $channelTitle }}</description>
        </image>
@foreach ($items as $item)
<item>
            <title><![CDATA[{{ $item['title'] }}]]></title>
            <link>{{ $item['url'] }}</link>
            <description><![CDATA[{{ $item['content'] }}]]></description>
            <author>{{ $item['author'] }}&#64;{{ $httpHost }} ({{ $item['author'] }})</author>
            <category domain="{{ $baseUrl }}/web/torrents?cat={{ $item['categoryId'] }}">{{ $item['categoryName'] }}</category>
            <comments><![CDATA[{{ $item['commentsUrl'] }}]]></comments>
            <enclosure url="{{ $item['downloadUrl'] }}" length="{{ $item['size'] }}" type="application/x-bittorrent" />
            <guid isPermaLink="false">{{ $item['guid'] }}</guid>
            <pubDate>{{ $item['pubDate'] }}</pubDate>
        </item>
@endforeach
</channel>
</rss>
