// Classic Editor plugin for collapsible sections: a <div class="collapsible"> with a
// heading and one or more editable <details class="collapsible-item"> items. TinyMCE 4.9 keeps
// every <details> expanded while editing and parks the stored open attribute in
// data-mce-open, which it writes back on save; that attribute therefore doubles as
// the "expanded by default" flag.
(function () {
    var GROUP = 'div.collapsible';
    var TITLE = 'h3.collapsible-title';
    var ITEM = 'details.collapsible-item';
    var OPEN_FLAG = 'data-mce-open';
    var EMPTY_FLAG = 'data-zw-empty';
    var BLOCK_PATTERN = /<(p|h[1-6]|ul|ol|blockquote|figure|div|table|pre)\b/i;
    var EMPTY_PARAGRAPH = '<p><br data-mce-bogus="1"></p>';
    var ENTER = 13;
    var BACKSPACE = 8;
    var DELETE = 46;

    tinymce.PluginManager.add('zw_collapsible', function (editor) {
        function closest(element, selector) {
            return editor.dom.getParent(element, selector);
        }

        function groupOf(element) {
            return closest(element, GROUP);
        }

        function itemOf(element) {
            return closest(element, ITEM);
        }

        function summaryOf(item) {
            return item.querySelector('summary');
        }

        function isEmpty(element) {
            return editor.dom.isEmpty(element);
        }

        // Nothing is selected between the range start and the edge of the block.
        function caretAtEdge(block, range, edge) {
            var probe = range.cloneRange();
            if (edge === 'start') {
                probe.setStart(block, 0);
                probe.setEnd(range.startContainer, range.startOffset);
            } else {
                probe.setStart(range.endContainer, range.endOffset);
                probe.setEnd(block, block.childNodes.length);
            }
            return probe.toString() === '';
        }

        function caretAtStartOf(element) {
            editor.selection.setCursorLocation(element, 0);
        }

        function caretAtEndOf(element) {
            editor.selection.select(element, true);
            editor.selection.collapse(false);
        }

        function selectContents(element) {
            editor.selection.select(element, true);
        }

        function createItem(body) {
            return editor.dom.create('details', {'class': 'collapsible-item', open: 'open'}, '<summary>Kop</summary>' + body);
        }

        // The editor stylesheet shows a placeholder in flagged headings; CSS alone
        // cannot tell an emptied heading (bogus <br>) from one with text and a <br>.
        function flagEmptyHeadings() {
            tinymce.each(editor.dom.select(TITLE + ', ' + ITEM + ' > summary'), function (heading) {
                if (isEmpty(heading)) {
                    heading.setAttribute(EMPTY_FLAG, '');
                } else {
                    heading.removeAttribute(EMPTY_FLAG);
                }
            });
        }

        editor.on('PreInit', function () {
            editor.serializer.addAttributeFilter(EMPTY_FLAG, function (nodes) {
                tinymce.each(nodes, function (node) {
                    node.attr(EMPTY_FLAG, null);
                });
            });
        });

        editor.on('SetContent NodeChange keyup input', flagEmptyHeadings);

        // The state label is the heading's ::after box, which sits at the right end of the bar.
        function clickedStateLabel(summary, e) {
            var win = editor.getWin();
            var width = parseFloat(win.getComputedStyle(summary, '::after').width) || 0;
            var paddingRight = parseFloat(win.getComputedStyle(summary).paddingRight) || 0;
            var right = summary.getBoundingClientRect().right - paddingRight;
            return width > 0 && e.clientX >= right - width && e.clientX <= right;
        }

        // Clicking a heading must place the caret, not collapse the item; clicking
        // its state label flips how the item opens on the site.
        editor.on('click', function (e) {
            var summary = closest(e.target, 'summary');
            var item = summary && itemOf(summary);
            if (!item) {
                return;
            }
            e.preventDefault();
            if (clickedStateLabel(summary, e)) {
                toggleOpenByDefault(item);
            }
        });

        editor.on('init', function () {
            editor.getBody().addEventListener('toggle', function (e) {
                if (editor.dom.is(e.target, ITEM) && !e.target.open) {
                    e.target.open = true;
                }
            }, true);

            // TinyMCE prepends its own delete handlers during init, so register after
            // init and prepend again to get in front of them.
            editor.on('keydown', onKeyDown, true);
        });

        // Later handlers (TinyMCE's own delete logic) would still act on the new caret position.
        function handled(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }

        function onKeyDown(e) {
            var dom = editor.dom;
            var start = editor.selection.getStart();
            var range = editor.selection.getRng();
            var block = dom.getParent(start, dom.isBlock);
            var group = groupOf(start);

            if (e.isDefaultPrevented()) {
                return;
            }

            // Delete just before a section would pull its title into the paragraph.
            if (!group) {
                if (e.keyCode === DELETE && range.collapsed && block && block.nextSibling && dom.is(block.nextSibling, GROUP) && caretAtEdge(block, range, 'end')) {
                    handled(e);
                }
                return;
            }

            var title = closest(start, TITLE);
            var item = itemOf(start);
            var summary = closest(start, 'summary');

            if (e.keyCode === ENTER && !e.shiftKey && (title || summary)) {
                // Enter in a heading moves on instead of breaking the heading.
                handled(e);
                if (!range.collapsed) {
                    editor.selection.setContent('');
                }
                if (title) {
                    var firstItem = group.querySelector(ITEM);
                    if (!firstItem) {
                        firstItem = createItem(EMPTY_PARAGRAPH);
                        group.appendChild(firstItem);
                    }
                    caretAtStartOf(summaryOf(firstItem));
                } else {
                    var next = summary.nextSibling;
                    if (!next || !dom.isBlock(next)) {
                        next = dom.create('p', {}, '<br data-mce-bogus="1">');
                        dom.insertAfter(next, summary);
                    }
                    caretAtStartOf(next);
                }
                return;
            }

            if (e.keyCode === ENTER && !e.shiftKey && block && block.parentNode === group && !title && isEmpty(block)) {
                // Enter on an empty line at the end of the section leaves it. The split
                // may have taken the last item's only paragraph along; give it a new one.
                handled(e);
                var lastItem = block.previousElementSibling;
                if (lastItem && dom.is(lastItem, ITEM) && !summaryOf(lastItem).nextSibling) {
                    lastItem.appendChild(dom.create('p', {}, '<br data-mce-bogus="1">'));
                }
                dom.insertAfter(block, group);
                caretAtStartOf(block);
                return;
            }

            if (!range.collapsed) {
                return;
            }

            if (e.keyCode === BACKSPACE) {
                if (summary && isEmpty(summary)) {
                    // An empty heading stays; an entirely empty item goes away.
                    handled(e);
                    if (item && isEmpty(item)) {
                        removeItem(item);
                    }
                    return;
                }
                if (title && (isEmpty(title) || caretAtEdge(title, range, 'start'))) {
                    handled(e);
                    return;
                }
                if (summary && caretAtEdge(summary, range, 'start')) {
                    // Would merge the heading into the title or the previous item.
                    handled(e);
                    return;
                }
                if (item && block && block.parentNode === item && block.previousSibling === summaryOf(item) && caretAtEdge(block, range, 'start')) {
                    // Would merge the text into the heading.
                    handled(e);
                }
                return;
            }

            if (e.keyCode === DELETE) {
                var heading = title || summary;
                if (heading && caretAtEdge(heading, range, 'end')) {
                    // Would pull the following text into the heading.
                    handled(e);
                    return;
                }
                if (item && block && block.parentNode === item && !block.nextSibling && caretAtEdge(block, range, 'end')) {
                    // Would pull the next heading into this item's text.
                    handled(e);
                }
            }
        }

        function removeItem(item) {
            editor.undoManager.transact(function () {
                var previous = item.previousElementSibling;
                editor.dom.remove(item);
                caretAtEndOf(editor.dom.is(previous, ITEM) ? previous.lastElementChild : previous);
            });
        }

        function insertSection() {
            var dom = editor.dom;
            if (groupOf(editor.selection.getStart())) {
                addItem();
                return;
            }

            var selection = editor.selection.getContent({format: 'html'});
            var body = BLOCK_PATTERN.test(selection) ? selection : '<p>' + selection + '</p>';

            editor.undoManager.transact(function () {
                editor.insertContent(
                    '<div class="collapsible" id="zw-collapsible-new"><h3 class="collapsible-title">Titel</h3>'
                    + '<details class="collapsible-item"><summary>Kop</summary>' + body + '</details></div>'
                );
                var group = dom.get('zw-collapsible-new');
                if (!group) {
                    return;
                }
                dom.setAttrib(group, 'id', null);
                // Leave the placeholder title selected so typing replaces it.
                selectContents(group.querySelector(TITLE));
            });
        }

        function addItem() {
            var start = editor.selection.getStart();
            var group = groupOf(start);
            if (!group) {
                return;
            }
            var current = itemOf(start);
            editor.undoManager.transact(function () {
                var item = createItem(EMPTY_PARAGRAPH);
                if (current) {
                    editor.dom.insertAfter(item, current);
                } else {
                    group.appendChild(item);
                }
                selectContents(summaryOf(item));
            });
            editor.nodeChanged();
        }

        function toggleOpenByDefault(item) {
            editor.undoManager.transact(function () {
                // Native setAttribute: TinyMCE's setAttrib drops empty values.
                if (item.hasAttribute(OPEN_FLAG)) {
                    item.removeAttribute(OPEN_FLAG);
                } else {
                    item.setAttribute(OPEN_FLAG, '');
                }
            });
            editor.nodeChanged();
        }

        // Turns a heading into a bold paragraph, or drops it when empty.
        function headingToParagraph(heading) {
            var dom = editor.dom;
            if (isEmpty(heading)) {
                dom.remove(heading);
                return;
            }
            var strong = dom.create('strong');
            while (heading.firstChild) {
                strong.appendChild(heading.firstChild);
            }
            var paragraph = dom.create('p');
            paragraph.appendChild(strong);
            dom.replace(paragraph, heading);
        }

        // Turns the section back into ordinary paragraphs.
        function unwrapSection() {
            var dom = editor.dom;
            var group = groupOf(editor.selection.getStart());
            if (!group) {
                return;
            }
            editor.undoManager.transact(function () {
                tinymce.each(dom.select(TITLE + ', ' + ITEM + ' > summary', group), headingToParagraph);
                tinymce.each(dom.select(ITEM, group), function (item) {
                    dom.remove(item, true);
                });
                dom.remove(group, true);
            });
            editor.nodeChanged();
        }

        editor.addButton('zw_collapsible', {
            icon: 'dashicon dashicons-plus-alt',
            tooltip: 'Uitklapbaar blok',
            onclick: insertSection,
        });

        editor.addButton('zw_collapsible_add', {
            text: 'Onderdeel toevoegen',
            tooltip: 'Voeg een uitklapbaar onderdeel toe',
            onclick: addItem,
        });

        editor.addButton('zw_collapsible_remove', {
            text: 'Blok opheffen',
            tooltip: 'Haal het blok weg; titel, koppen en tekst blijven staan',
            onclick: unwrapSection,
        });

        editor.addContextToolbar(GROUP, 'zw_collapsible_add zw_collapsible_remove');
    });
})();
