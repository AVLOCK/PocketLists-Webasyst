(function () {
    // todo: refactor...
    var list_id = parseInt($('#pl-list-id').val()),
        pocket_id = parseInt($('#pl-pocket-id').val()),
        $list_items_wrapper = $('#pl-list-items'),
        $undone_items_wrapper = $('#pl-undone-items > ul.menu-v'),
        $done_items_wrapper = $('#pl-complete-log > ul.menu-v'),
        $loading = $('<i class="icon16 loading">'),
        $new_list_inpit = $('#pl-new-list-input'),
        $new_item_wrapper = $('#pl-item-add').detach(),
        $new_item_input = $new_item_wrapper.find('textarea'),
        $new_item_wrapper_hover = $('<div id="pl-item-add-wrapper-hover" style="display: none;">'),
        item_selector = '[data-parent-id]',
        $add_item_link = $('#pl-item-add-link');

    var init_sortable = function () {
        $('#pl-undone-items ul.menu-v').sortable({
            item: item_selector,
            connectWith: "ul.menu-v",
            placeholder: 'pl-item-placeholder',
            tolerance: 'pointer',
            stop: function (event, ui) {
                var $prev = ui.item.parents(item_selector).first(),
                    parent_id = $prev.length ? parseInt($prev.data('id')) : 0;

                ui.item.data('parent-id', parent_id);
                update_sort.call(ui.item, parseInt(ui.item.data('id')));
            }
        });
    };
    var update_list = function (id) {
        var data = {
            name: $(this).val().trim(),
            type: 'checklist',
            pocket_id: pocket_id
        };
        if (data.name) {
            $(this).after($loading);
            $.post(
                '?module=list&action=update',
                {
                    data: data,
                    id: id
                },
                function (r) {
                    if (r.status === 'ok') {
                        if (list_id === -1) {
                            $.wa.setHash('#/pocket/' + pocket_id + '/list/' + r.data.id + '/');
                        }
                    } else {

                    }
                },
                'json'
            );
        }
    };
    var add_items = function (data, callback) {
        var $this = $(this);
        $this.after($loading);
        $.post(
            '?module=item&action=create',
            {
                list_id: list_id,
                data: data
            },
            function (html) {
                var $li = $this.closest(item_selector);
                var $html = $('' + html + '');

                $new_item_input.data('can_blur', false);

                if ($li.length) {
                    if (!$li.parents(item_selector).length || // not root item
                        //$li.prev(item_selector).length || // not first item in subs
                        $new_item_wrapper.prev('.pl-item').length) { // new item wrapper is after item
                        $li.after($html);
                    } else {
                        $li.before($html);
                    }
                } else {
                    $undone_items_wrapper.prepend($html);
                }
                $html.filter(item_selector).last()
                    .find('.pl-item').first().after($new_item_wrapper);

                $loading.remove();
                $('.pl-list-empty').removeClass('pl-list-empty');

                $new_item_input.val('').trigger('focus').css('height', 'auto').data('can_blur', true);

                $.isFunction(callback) && callback.call($this);

                update_list_count_badge();
                update_sort.call($html);
            }
        );
    };
    var move_item = function (data, callback) {
        $.post(
            '?module=item&action=move',
            {
                list_id: list_id,
                data: data
            },
            function (r) {
                if (r.status === 'ok') {
                    $.isFunction(callback) && callback.call();
                } else {
                    alert(r.errors);
                }
            },
            'json'
        );
    };
    var get_items = function () {
        var data = [];
        $undone_items_wrapper.find(item_selector).each(function (i) {
            var $this = $(this);
            data.push({
                id: $this.data('id'),
                parent_id: $this.data('parent-id'),
                sort: i,
                has_children: $this.find(item_selector).length ? 1 : 0
            });
        });
        return data;
    };
    var update_sort = function (id) {
        this.find('label').first().append($loading);
        $.post(
            '?module=item&action=sort',
            {
                list_id: list_id,
                item_id: id ? id : 0,
                data: get_items()
            },
            function (r) {
                if (r.status === 'ok') {
                    init_sortable();
                } else {
                    alert(r.errors);
                }
                $loading.remove();
            },
            'json'
        );
    };
    var complete_item = function (id, status, callback) {
        var $item = this;
        $item.find('.pl-select-label').first().append($loading);
        $item.prop('disabled', true);
        $.post(
            '?module=item&action=complete',
            {
                list_id: list_id,
                id: id,
                status: status
            },
            function (r) {
                if (r.status === 'ok') {
                    $.pocketlists.updateAppCounter();
                    // remove from undone list
                    $item.find('ul.menu-v').find(':checkbox').prop('checked', status); // check nesting items
                    $item.find('.pl-done').prop('disabled', false);
                    $item.find('.pl-item-name').toggleClass('gray');
                    setTimeout(function () {
                        $item.slideToggle(200, function () {
                            $item.show();
                            if (status) {
                                $done_items_wrapper.append($item);
                            } else {
                                $undone_items_wrapper.append($item);
                                update_sort.call($item);
                            }

                            // always update list count icon
                            update_list_count_badge();

                            $('#pl-complete-log-link').find('i').text($_('Show all ' + $done_items_wrapper.find('[data-id]').length + ' completed to-dos'));

                            callback && $.isFunction(callback) && callback.call($item);
                        });
                    }, 500);

                } else {
                    alert(r.errors);
                }
                $loading.remove();
            },
            'json'
        );
    };
    var increase_item = function (e) {
        var $items = $undone_items_wrapper.find('.pl-item-selected').closest(item_selector);
        if ($items.length) {
            e.preventDefault();
            e.stopPropagation();
            $items.each(function () {
                var $item = $(this),
                    $prev = $item.prev(item_selector);
                if ($prev.length) { // not first
                    var parent_id = parseInt($prev.data('id'));
                    $item.data('parent-id', parent_id); // update parent id

                    var $nested = $prev.find('ul.menu-v').first();
                    if ($nested.length) {
                        $nested.append($item);
                    } else {
                        $prev.append($('<ul class="menu-v">').html($item));
                    }

                    update_sort.call($item, parseInt($item.data('id')));
                }
            });
        }
    };
    var decrease_item = function (e) {
        var $items = $undone_items_wrapper.find('.pl-item-selected').closest(item_selector);
        if ($items.length) {
            e.preventDefault();
            e.stopPropagation();
            $items.each(function () {
                var $item = $(this),
                    $prev = $item.parents(item_selector).first();
                if ($prev.length) { // not first level
                    var parent_id = parseInt($prev.data('parent-id'));

                    $item.data('parent-id', parent_id); // update parent id

                    var $items_same_level = $item.nextAll(), // all next items on same level
                        $item_children_wrapper = $item.find('ul.menu-v'); // item children wrapper

                    if (!$item_children_wrapper.length) { // create if not exist
                        $item_children_wrapper = $('<ul class="menu-v">');
                        $item.append($item_children_wrapper);
                    }
                    $item_children_wrapper.append($items_same_level); // now will be children of current

                    $prev.after($item);

                    update_sort.call($item, parseInt($item.data('id')));
                }
            });
        }
    };
    var favorite = function(type, id) {
        var $star = this.find('[class*="star"]');
        $.post(
            '?module=' + type + '&action=favorite',
            {
                id: id,
                status: $star.hasClass('star-empty') ? 1 : 0
            },
            function (r) {
                if (r.status === 'ok') {
                    $star.toggleClass('star-empty star')
                } else {
                    alert(r.errors);
                }
                //$loading.remove();
            },
            'json'
        );
    };
    var update_list_count_badge = function() {
        $('#pl-lists')
            .find('[data-pl-list-id="' + list_id + '"]')
            .find('.count').text($undone_items_wrapper.find('[data-id]').length);
    };

    var init = function() {
        if ($.pocketlists_routing.getHash() == '#/todo/' && $.pocketlists_routing.getHash().indexOf('/team/') > 0) {
            $new_item_wrapper.prependTo($undone_items_wrapper).slideDown(200).wrap('<li class="pl-new-item-wrapper">');
            $new_item_input.focus();
        }
    };

    function stickyDetailsSidebar()
    {
        var list_top_offset = $('#pl-list-content').offset().top;
        var _viewport_top_offset = $(window).scrollTop();
        var _window_height = $(window).height();

        if ($('.pl-details .fields form').height() > _window_height)
            return;

        if ( _viewport_top_offset > list_top_offset)
        {
            $('.pl-details').addClass('sticky');
            var _viewport_bottom_offset = $(document).height() - _window_height - _viewport_top_offset;

            $('.pl-details').css('bottom', Math.max(0, 16-_viewport_bottom_offset)+'px');

        }
        else
        {
            $('.pl-details').removeClass('sticky');
        }
    }

    init();

    if ($new_list_inpit.length) {
        $new_list_inpit.focus();
        $new_list_inpit.on('keydown', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                update_list.call(this, list_id);
            }
        });
    }
    $add_item_link.on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        function show_new_item_wrapper() {
            $new_item_wrapper.prependTo($undone_items_wrapper).slideDown(200, function () {
                $new_item_input.focus();
            }).wrap('<li class="pl-new-item-wrapper">');
        }
        if ($new_item_wrapper.is(':visible')) {
            $new_item_wrapper.slideUp(200, function () {
                $new_item_wrapper.detach();
                $('.pl-new-item-wrapper').remove();
                show_new_item_wrapper();
            });
        } else {
            show_new_item_wrapper();
        }

    });
    if ($('.pl-list-empty').length) {
        $add_item_link.trigger('click');
    }

    function resizeTextarea () {
        $new_item_input.css('height', 'auto');
        $new_item_input.css('height', ($new_item_input.get(0).scrollHeight - parseInt($new_item_input.css('padding-top')) - parseInt($new_item_input.css('padding-bottom'))) + 'px');
    }

    function hide_new_item_wrapper() {
        $new_item_wrapper.slideUp(200, function () {
            $new_item_wrapper.detach();
            $('.pl-new-item-wrapper').remove();
            $new_item_input.val('');
        });
    }

    $new_item_input
        .on('change cut keydown drop paste', function() {
            window.setTimeout(resizeTextarea, 0);
        })
        .on('keydown', function (e) {
            var $this = $(this);
            $this.data('can_blur', true);
            if (!e.shiftKey && e.which === 13) {
                e.preventDefault();
                var parent_id = $this.closest('.menu-v').find(item_selector).first().data('parent-id'),
                    name = $this.val().trim();
                add_items.call(this, [{
                    name: $this.val().trim(),
                    parent_id: parent_id
                }]);
            } else if (e.which === 27) {
                hide_new_item_wrapper();
            }
        })
        .on('paste', function (e) {
            var parent_id = $(this).closest('.menu-v').find(item_selector).first().data('parent-id');
            var self = this;
            setTimeout(function () {
                var items = $new_item_input.val().split(/\n/);
                var data = [];
                if (items.length > 1) {
                    for (var i = 0; i < items.length; i++) {
                        var name = $.trim(items[i]);
                        if (name) {
                            data.push({
                                name: name,
                                parent_id: parent_id
                            });
                        }
                    }
                    add_items.call(self, data);
                }
            }, 100);
        })
        .on('blur', function() {
            var $this = $(this),
                parent_id = $this.closest('.menu-v').find(item_selector).first().data('parent-id'),
                name = $this.val().trim(),
                can_blur = $this.data('can_blur');

            if (can_blur) {
                if (name) {
                    add_items.call(this, [{
                        name: name,
                        parent_id: parent_id
                    }], hide_new_item_wrapper);
                } else {
                    hide_new_item_wrapper();
                }
            }
        });

    var undone_items_wrapper_hover_timeout = null;
    $undone_items_wrapper
        .on('mouseenter', item_selector + ' > .pl-item', function (e) {
            e.stopPropagation();
            var $item = $(this);
            undone_items_wrapper_hover_timeout = setTimeout(function(){
                if (!$item.find($new_item_wrapper).length) { // if no placeholder here
                    $item.find('.pl-select-label').append($new_item_wrapper_hover.show());
                }
            }, 500);
        })
        .on('mouseleave', item_selector + ' > .pl-item', function (e) {
            clearTimeout(undone_items_wrapper_hover_timeout);
            $new_item_wrapper_hover.detach();
        });

    $new_item_wrapper_hover.on('click', function (e) {
        // if item has children - place it before first
        var $item = $(this);
        var $has_children = $item.closest(item_selector).find('.menu-v');

        $new_item_input.data('can_blur', false);
        if ($has_children.length) { // if item has children - indent
            $has_children.find('.pl-item').first().before($new_item_wrapper);
        } else { // else on same level
            $item.closest(item_selector).find('.pl-item').first().after($new_item_wrapper);
        }
        $new_item_wrapper_hover.detach();
        $new_item_wrapper.slideDown(200);
        $new_item_input.focus();
        $new_item_input.data('can_blur', true);
    });

    $list_items_wrapper.on('change', '.pl-is-selected', function (e) {
        var $this = $(this),
            $details = $('#pl-item-details'),
            details_shown = $details.is(':visible'),
            $item_content_wrapper = $this.closest('.pl-item'),
            $item_wrapper = $item_content_wrapper.closest(item_selector),
            is_selected = $item_content_wrapper.hasClass('pl-item-selected');

        e.preventDefault();

        if (!$item_wrapper.find('#pl-item-add').length) {
            if (!details_shown && !is_selected) { // on first click - select
                $details.hide();
                $list_items_wrapper.find('.pl-item').removeClass('pl-item-selected');
                $item_content_wrapper.addClass('pl-item-selected');
                $this.prop('checked', true);
            } else if (!details_shown) { // on second - show details
                $.pocketlists.scrollToTop(200, 80);
                $details.html($loading).show();
                stickyDetailsSidebar();
                $.post(
                    '?module=item&action=details',
                    {
                        id: parseInt($item_wrapper.data('id'))
                    },
                    function (html) {
                        $details.html(html);
                        item_details($details);
                    }
                );
                $this.prop('checked', true);
            } else { // on third
                $details.hide().empty();
                $list_items_wrapper.find('.pl-item').removeClass('pl-item-selected');
                $this.prop('checked', false);
            }
        }
    });

    // action: complete item
    $list_items_wrapper.on('change', '.pl-done', function (e) {
        var $this = $(this),
            $item = $this.closest(item_selector),
            id = parseInt($item.data('id')),
            status = $this.is(':checked') ? 1 : 0;

        complete_item.call($item, id, status);
    });

    // fav item
    $list_items_wrapper.on('click', '.pl-favorite', function(e) {
        e.preventDefault();
        var $this = $(this),
            $item = $this.closest(item_selector),
            id = parseInt($item.data('id'));
        favorite.call($item, 'item', id);
    });

    $(document).on('keydown', function (e) {
        switch (e.which) {
            //case 39: // -->
            //    increase_item.call(this);
            //    break;
            case 9: // tab
                if (!$('#pl-list-details').is(':visible') && !$('#pl-item-details').is(':visible')) {
                    if (e.shiftKey) {
                        decrease_item.call(this, e);
                    } else {
                        increase_item.call(this, e);
                    }
                }
                break;
            //case 37: // <--
            //    decrease_item.call(this);
            //    break;
            case 27: // esc
                if ($('#pl-list-details').is(':visible')) {
                    $('#pl-list-details').hide().empty();
                    $('.pl-list-title').removeData('pl-clicked');
                }
                if ($('#pl-item-details').is(':visible')) {
                    $('#pl-item-details').hide().empty();
                    var $selected = $list_items_wrapper.find('.pl-item-selected');
                    $selected.removeClass('pl-item-selected').prop('checked', false);
                }
                break;
        }
    });

    init_sortable();

    var item_details = function ($wrapper) {
        var id = 0;
        var init = function () {
            var datepicker_options = {
                changeMonth: true,
                changeYear: true,
                shortYearCutoff: 2,
                dateShowWeek: false,
                showOtherMonths: true,
                selectOtherMonths: true,
                stepMonths: 1,
                numberOfMonths: 1,
                gotoCurrent: true,
                constrainInput: false,
                minDate: new Date()
            };

            $wrapper.find('#pl-item-due-datetime').datepicker(datepicker_options);

            id = parseInt($wrapper.find('input[name="item\[id\]"]').val());
            handlers();
        };
        var handlers = function () {
            // save
            $wrapper.find('form').on('submit', function (e) {
                e.preventDefault();
                var $this = $(this);
                $this.find('#pl-item-details-save').after($loading);
                $.post('?module=item&action=data', $this.serialize(), function (html) {
                    $.pocketlists.updateAppCounter();
                    $loading.remove();
                    $list_items_wrapper.find('[data-id="' + id + '"] > .pl-item').replaceWith($(html).addClass('pl-item-selected'));
                    $wrapper.hide().empty();
                });
                return false;
            });
            // cancel
            $wrapper.find('#pl-item-details-cancel').on('click', function (e) {
                e.preventDefault();
                $wrapper.hide().empty();
                $('.pl-list-title').removeData('pl-clicked');
            });
            $wrapper.find('#pl-item-priority a').on('click', function (e) {
                e.preventDefault();
                $('#pl-item-priority').find('input').val($(this).data('pl-item-priority'));
                $(this).addClass('selected')
                    .siblings().removeClass('selected')
            });
            $wrapper.find('[data-pl-action="item-delete"]').on('click', function (e) {
                e.preventDefault();

                $('#pl-dialog-delete-confirm').waDialog({
                    'height': '150px',
                    'min-height': '150px',
                    'width': '400px',
                    onLoad: function () {
                        var $this = $(this);
                    },
                    onSubmit: function (d) {
                        $.post('?module=item&action=delete', {id: id, list_id: list_id}, function (r) {
                            if (r.status === 'ok') {
                                $list_items_wrapper.find('[data-id="' + r.data.id + '"]').remove();
                                $wrapper.find('#pl-item-details-cancel').trigger('click');
                                d.trigger('close');
                            } else {

                            }
                        }, 'json');
                        return false;
                    }
                });
            });
            $wrapper.on('change', '#pl-assigned-contact select', function() {
                var assigned_contact_id = $(this).val();
                $('#pl-assigned-contact').find('[data-pl-contact-id="' + assigned_contact_id + '"]').show().siblings().hide();
            });
        };

        init();
    };

    $('.pl-list-title')
        .on('click', function (e) {
            var $details = $('#pl-list-details'),
                $this = $(this),
                clicked = $this.data('pl-clicked');

            if ($(e.target).closest('.pl-done-label').length) {
                return;
            }
            if (clicked == 1) {
                $.pocketlists.scrollToTop(200, 80);
                $details.html($loading).show();
                stickyDetailsSidebar();
                $this.data('pl-clicked', 2);
                $.post(
                    '?module=list&action=details',
                    {
                        id: parseInt($('#pl-list-id').val())
                    },
                    function (html) {
                        $details.html(html);
                        list_details($details);
                    }
                );
            } else if (clicked == 2) {
                $this.removeData('pl-clicked');
                $details.hide().empty();
            } else {
                $this.data('pl-clicked', 1);
            }
        })
        .on('click', '[data-pl-action="list-delete"]', function (e) {
            e.preventDefault();

            $('#pl-dialog-delete-confirm').waDialog({
                'height': '150px',
                'min-height': '150px',
                'width': '400px',
                onLoad: function () {
                    var $this = $(this);
                },
                onSubmit: function (d) {
                    $.post('?module=list&action=delete', {list_id: list_id}, function (r) {
                        if (r.status === 'ok') {
                            d.trigger('close');
                            $.wa.setHash('#/pocket/1/');
                        } else {

                        }
                    }, 'json');
                    return false;
                }
            });
        })
        .on('click', '[data-pl-action="list-archive"]', function (e) {
            e.preventDefault();

            $.post('?module=list&action=archive', {list_id: list_id, archive: 1}, function (r) {
                if (r.status === 'ok') {
                    $.wa.setHash('#/pocket/1/');
                } else {

                }
            }, 'json');
        })
        .on('click', '[data-pl-action="list-sort"]', function (e) {
            e.preventDefault();

            $.post('?module=list&action=sort', {list_id: list_id}, function (r) {
                if (r.status === 'ok') {
                    $.pocketlists_routing.redispatch();
                } else {

                }
            }, 'json');
        })
        .on('click', '[data-pl-action="list-favorite"]', function (e) {
            e.preventDefault();
            var $this = $(this),
                $list = $this.closest('.pl-list-title');

            favorite.call($list, 'list', $list.find('#pl-list-id').val());
        });

    var list_details = function ($wrapper) {
        var list_id = 0;
        var icon_path = $wrapper.find('#pl-list-icon-dialog').find('ul').data('pl-icons-path');
        var init = function () {
            handlers();
            list_id = parseInt($wrapper.find('input[name="list\[id\]"]').val());
        };
        var handlers = function () {
            // save
            $wrapper.find('form').on('submit', function (e) {
                e.preventDefault();
                var $this = $(this);
                $this.find('#pl-list-details-save').after($loading);
                $.post('?module=list&action=save', $this.serialize(), function (r) {
                    $loading.remove();
                    if (r.status === 'ok') {
                        update_list_list();
                        $wrapper.find('.success').show().delay(3000).hide();
                    } else {
                        $wrapper.find('.error').show().delay(3000).hide();
                    }
                    $wrapper.data('pl-clicked', 1).hide();
                }, 'json');
                return false;
            });
            // cancel
            $wrapper.find('#pl-list-details-cancel').on('click', function (e) {
                e.preventDefault();
                $wrapper.hide().empty();
            });
            $wrapper.find('#pl-list-color a').on('click', function (e) {
                e.preventDefault();
                $('#pl-list-color').find('input').val($(this).data('pl-list-color'));
                $(this).addClass('selected')
                    .siblings().removeClass('selected')
            });
            $wrapper.find('#pl-list-icon-change a').on('click', function (e) {
                e.preventDefault();
                var $this = $(this);

                $('#pl-list-icon-dialog').waDialog({
                    onLoad: function () {
                        var d = $(this);

                        $('#pl-list-icon-dialog').on('click', 'a[data-pl-list-icon]', function (e) {
                            e.preventDefault();
                            var icon = $(this).data('pl-list-icon');
                            $wrapper.find('#pl-list-icon-change').find('input').val($(this).data('pl-list-icon'));

                            $this.find('input').val(icon);
                            $wrapper.find('#pl-list-icon-change .listicon48').css('background-image', 'url(' + icon_path + icon + ')');
                            d.trigger('close');
                            return false;
                        })
                    }
                });
            });
        };
        var update_list_list = function () {
            // update name
            var name = $wrapper.find('input[name="list\[name\]"]').val(),
                color = $wrapper.find('[data-pl-list-color].selected').data('pl-list-color'),
                icon = $wrapper.find('input[name="list\[icon\]"]').val();
            $('#pl-list-name').text(name);
            // update color
            $('#pl-lists')
                .find('[data-pl-list-id="' + list_id + '"]').removeClass().addClass('pl-' + color)
                .find('.pl-list-name').text(name)
                .end()
                .find('.listicon48').css('background-image', 'url(' + icon_path + icon + ')');
            $('.pl-items').removeClass().addClass('pl-items pl-' + color);
            // update icon
        };

        init();
    };

    $('#pl-list-complete').on('click', function (e) {
        e.stopPropagation();

        $('#pl-dialog-list-archive-complete-all').waDialog({
            'height': '150px',
            'min-height': '150px',
            'width': '400px',
            onLoad: function () {
                var $this = $(this);
                $this.on('click', '[data-pl-action]', function (e) {
                    e.preventDefault();

                    var $button = $(this),
                        action = $button.data('pl-action');

                    $button.after($loading);

                    if (action === 'list-complete-all') {
                        $.post('?module=item&action=complete', {list_id: list_id, status: 1, id: -1}, function (r) {
                            if (r.status === 'ok') {
                                $this.trigger('close');
                                //$.wa.setHash('#/pocket/' + pocket_id + '/list/' + list_id );
                                $.pocketlists_routing.redispatch();
                            } else {
                            }
                        }, 'json');
                    } else if (action === 'list-archive') {
                        $.post('?module=list&action=archive', {list_id: list_id, archive: 1}, function (r) {
                            if (r.status === 'ok') {
                                $this.trigger('close');
                                $.wa.setHash('#/pocket/' + pocket_id);
                            } else {
                            }
                        }, 'json');
                    } else if (action === 'cancel') {
                        $('#pl-list-complete').prop('checked', false);
                        $this.trigger('close');
                        $loading.remove();
                    }
                });
            }
        });
    });

    $('#pl-complete-log-link').click(function () {
        $('#pl-complete-log').slideDown(200);
        $(this).slideUp(200);
        return false;
    });

    $(window).scroll(function() {
        stickyDetailsSidebar();
    });
}());
