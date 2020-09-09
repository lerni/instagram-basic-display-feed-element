<%-- $InstagramFeed.Profile.username --%>
<% if $HTML %>$HTML<% end_if %>
<% if $InstagramFeed.Media %>
    <div class="instafeed">
    <% loop $InstagramFeed.Media %>
        <% if $media_type == "IMAGE" || $media_type == "CAROUSEL_ALBUM" %>
        <a class="$media_type.LowerCase" href="$permalink" target="_blank" rel="noopener">
            <img loading="auto" src="$media_url" alt="$caption" />
        </a>
        <% end_if %>
    <% end_loop %>
    </div>
<% end_if %>
