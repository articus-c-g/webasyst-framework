// MAIN APP CONTROLLER
( function($) { "use strict";

    $.ajaxSetup({ cache: false });

    $.wa_ui = $.extend($.wa_ui || {}, {
        lang: false,
        app_url: false,
        backend_url: false,
        is_debug: false,

        // ContentRouter
        content: false,

        // UISidebar
        sidebar: false,

        title: {
            pattern: "%s - UI",
            set: function( title_string ) {
                if (title_string) {
                    var state = history.state;
                    if (state) {
                        state.title = title_string;
                    }
                    document.title = $.wa_ui.title.pattern.replace("%s", title_string);
                }
            }
        },
        init: {},

        start: function() {
            initCodePreview();
        },
        escape: function(string) {
            return $("<div />").text(string).html();
        }
    });

    // ContentRouter implements AJAX-based navigation
    // Initialized in layouts/Default.html
    var ContentRouter = ( function($) {

        ContentRouter = function(options) {
            var that = this;

            // DOM
            that.$window = $(window);
            that.$content = options["$content"];

            // VARS
            that.api_enabled = !!(window.history && window.history.pushState);

            // METHODS
            that.onLoad = (typeof options["onLoad"] === "function" ? options["onLoad"] : function() {});

            // DYNAMIC VARS
            that.xhr = false;
            that.is_enabled = true;
            that.need_confirm = false;

            // INIT
            that.init();
        };

        ContentRouter.prototype.init = function() {
            var that = this;
            //
            that.bindEvents();
        };

        ContentRouter.prototype.bindEvents = function() {
            var that = this;

            // When user clicks a link that leads to app backend, load content via XHR instead.
            var full_app_url = window.location.origin + $.wa_ui.app_url;

            $(document).on("click", "a", function(event) {
                var $link = $(this),
                    href = $link.attr("href");

                // hack for jqeury ui links without href attr
                if (!href) {
                    $link.attr("href", "javascript:void(0);");
                    href = $link.attr("href");
                }

                var stop_load = $link.hasClass("js-disable-router"),
                    is_app_url = ( this.href.substr(0, full_app_url.length) == full_app_url ),
                    is_normal_url = ( !(href === "#" || href.substr(0, 11) === "javascript:") ),
                    use_content_router = ( that.is_enabled && !stop_load && is_app_url && is_normal_url );

                if (!event.ctrlKey && !event.shiftKey && !event.metaKey && use_content_router) {
                    event.preventDefault();

                    var content_uri = this.href;

                    if (that.need_confirm) {
                        that.need_confirm = false;
                        console.log('WARNING: confirmation dialog is not implemented yet'); // !!!
                    }

                    that.load(content_uri);
                }
            });

            // App icon in global header
            $("#wa-app-ui").on("click", "a", function(event) {
                return false;
            });

            // Click on header app icon
            if (that.api_enabled) {
                window.onpopstate = function(event) {
                    event.stopPropagation();
                    that.onPopState(event);
                };
            }

            $(document).on("unsavedChanges", function(event, _need_confirm) {
                that.need_confirm = !!_need_confirm;
            });
        };

        ContentRouter.prototype.load = function(content_uri, unset_state) {
            var that = this;

            var uri_has_app_url = ( content_uri.indexOf( $.wa_ui.app_url ) >= 0 );
            if (!uri_has_app_url) {
                return false;
            }

            that.animate( true );

            if (that.xhr) {
                that.xhr.abort();
                $(document).trigger("wa_abort");
            }

            $(document).trigger('wa_before_load', {
                // for which these data ?
                content_uri: content_uri
            });

            that.xhr = $.ajax({
                method: 'GET',
                url: content_uri,
                dataType: 'html',
                global: false,
                cache: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();

                    xhr.addEventListener("progress", function(event) {
                        $(document).trigger("wa_loading", event);
                    }, false);

                    xhr.addEventListener("abort", function(event) {
                        console.log("abort", event);
                    }, false);

                    return xhr;
                }
            }).done(function(html) {
                if (that.api_enabled && !unset_state) {
                    history.pushState({
                        reload: true,               // force reload history state
                        content_uri: content_uri    // url, string
                    }, "", content_uri);
                }

                that.xhr = false;
                that.animate( false );
                that.setContent( html );

                setTimeout(function() {
                    that.onLoad(that);
                    $(document).trigger("wa_loaded");
                }, 0);

            }).fail(function(data) {
                console.log('Error loading data from ', content_uri, data);
                setTimeout(function() {
                    $(document).trigger("wa_loaded");
                }, 0);
                if (data.responseText) {
                    $.wa_ui.showErrorHtml(data.responseText);
                }
            }).always(function() {
                that.xhr = false;
            });

            return that.xhr;
        };

        ContentRouter.prototype.reload = function() {
            var that = this,
                content_uri = (that.api_enabled && history.state && history.state.content_uri) ? history.state.content_uri : location.href;

            if (content_uri) {
                return that.load(content_uri, true);
            } else {
                return $.when(); // a resolved promise
            }
        };

        ContentRouter.prototype.setContent = function( html ) {
            var that = this;

            $(document).trigger("wa_before_render");

            that.$content.html( html );
        };

        ContentRouter.prototype.onPopState = function(event) {
            var that = this,
                state = ( event.state || false );

            if (state) {
                if (!state.content_uri) {
                    return false;
                }

                $(document).trigger("wa_before_load");

                // CONTENT
                if (state.reload) {
                    that.reload( state.content_uri );
                } else if (state.content) {
                    that.setContent( state.content );
                }

                // TITLE
                if (state.title) {
                    $.wa_ui.title.set(state.title);
                }

                $(document).trigger("wa_loaded");
            } else {
                location.reload();
            }
        };

        ContentRouter.prototype.animate = function( show ) {
            // animation while loading could have been here...
        };

        ContentRouter.prototype.setUrl = function( url ) {
            var that = this;

            if (that.api_enabled) {
                history.pushState(null, null, url);
            } else {
                that.load(url, true);
            }

        };

        return ContentRouter;

    })(jQuery);

    var Sidebar = ( function($) {

        Sidebar = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // VARS

            // DYNAMIC VARS

            // INIT
            that.init();
        };

        Sidebar.prototype.init = function() {
            var that = this;

            that.$wrapper.on("click", ".menu > li > a", function() {
                var $link = $(this),
                    $li = $link.closest("li"),
                    active_class = "selected";

                that.$wrapper.find(".menu > li." + active_class).removeClass(active_class);
                $li.addClass(active_class);
            });
        };

        return Sidebar;

    })(jQuery);

    //

    $.wa_ui.init.initSidebar = function(options) {
        return new Sidebar(options);
    };
    $.wa_ui.init.initContentRouter = function(options) {
        return new ContentRouter(options);
    };

    function initCodePreview() {

        init();

        $(document).on("click", ".u-component-page .js-toggle-code", function(event) {
            event.preventDefault();
            $(this).closest(".u-code-wrapper").toggleClass("is-extended");
            init();
        });

        $(document).on("wa_loaded", init);

        function init() {
            $("textarea.u-code-preview:visible").each( function() {
                var $target = $(this);

                setHeight($target);

                $(window).on("resize", function() {
                    setHeight($target);
                });
            });
        }

        function setHeight($target) {
            $target.css("min-height", "");

            var target_h = $target.outerHeight(),
                target_pad = target_h - $target.height(),
                scroll_h = $target[0].scrollHeight;

            if (scroll_h > target_h) {
                $target.css("min-height", scroll_h + target_pad);
            }
        }
    }

})($);