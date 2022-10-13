<%-- $InstagramFeed.Profile.username --%>
<% if $HTML %>$HTML<% end_if %>
<% if $InstagramFeed.Media %>
    <div class="instafeed">
    <% loop $InstagramFeed.Media %>
        <% if $media_type == "IMAGE" %>
            <a class="$media_type.LowerCase" href="$permalink" target="_blank" rel="noopener">
                <figure >
                    <img loading="auto" src="$media_url" alt="$caption" />
                    <figcaption>{$caption}<span data-feather="instagram"></span></figcaption>
                </figure>
            </a>
        <% end_if %>
        <% if $media_type == "CAROUSEL_ALBUM" %>
            <% loop $Children.Limit(1) %><%-- per default we show just one - may just incrase limit? --%>
                <% if $media_type == "VIDEO" %>
                    <a class="$media_type.LowerCase" href="$permalink" target="_blank" rel="noopener">
                        <figure >
                            <video muted poster="$thumbnail_url" width="100%" autoplay loop playsinline>
                                <source src="$media_url" type="video/mp4">
                            </video>
                            <figcaption>
                                {$Up.Up.caption}
                                <span data-feather="instagram"></span>
                            </figcaption>
                        </figure >
                    </a>
                <% else_if $media_type == "IMAGE" %>
                    <a class="$media_type.LowerCase" href="$permalink" target="_blank" rel="noopener">
                        <figure >
                            <img loading="auto" src="$media_url" alt="$caption" />
                            <figcaption>{$Up.Up.caption}<span data-feather="instagram"></span></figcaption>
                        </figure>
                    </a>
                <% end_if %>
            <% end_loop %>
        <% end_if %>
        <% if $media_type == "VIDEO" %>
            <a class="$media_type.LowerCase" href="$permalink" target="_blank" rel="noopener">
                <figure >
                    <video muted poster="$thumbnail_url" width="100%" autoplay loop playsinline>
                        <source src="$media_url" type="video/mp4">
                    </video>
                    <figcaption>
                        {$Up.Up.caption}
                        <span data-feather="instagram"></span>
                    </figcaption>
                <figure >
            </a>
        <% end_if %>
    <% end_loop %>
    </div>
<% end_if %>
