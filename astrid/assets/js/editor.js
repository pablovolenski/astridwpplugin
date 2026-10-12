(function (wp) {
  'use strict';
  var el = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var useSelect = wp.data.useSelect;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var InspectorControls = wp.blockEditor.InspectorControls;
  var PanelBody = wp.components.PanelBody;
  var SelectControl = wp.components.SelectControl;
  var TextControl = wp.components.TextControl;
  var TextareaControl = wp.components.TextareaControl;
  var Notice = wp.components.Notice;
  var settings = window.astridEditor.settings;
  var labels = window.astridEditor.labels;

  function plainText(html) {
    var doc = new DOMParser().parseFromString(html || '', 'text/html');
    return (doc.body.textContent || '').trim();
  }

  function groupHeading(blocks, postTitle) {
    for (var i = 0; i < blocks.length; i++) {
      var block = blocks[i];
      if (block.name === 'core/heading') {
        var title = plainText(block.attributes.content);
        if (title) return title;
      }
      if (block.name === 'core/post-title' && postTitle) return postTitle;
      if (block.name !== 'core/group' && block.innerBlocks && block.innerBlocks.length) {
        var nested = groupHeading(block.innerBlocks, postTitle);
        if (nested) return nested;
      }
    }
    return '';
  }

  function template(text, activity) {
    return text.replace(/\{activity\}/g, function () { return activity; }).replace(/\{option\}/g, '');
  }

  function buttonStyle() {
    if (settings.button_style !== 'custom') return {};
    return {
      backgroundColor: settings.button_background, color: settings.button_color,
      borderRadius: settings.button_radius + 'px',
      padding: settings.button_padding_y + 'px ' + settings.button_padding_x + 'px',
      fontSize: settings.button_font_size + 'px', border: 0
    };
  }

  function Edit(props) {
    var attributes = props.attributes;
    var multiple = props.name === 'astrid/multiple-options';
    var activity = useSelect(function (select) {
      var store = select('core/block-editor');
      var editor = select('core/editor');
      var postTitle = editor && editor.getEditedPostAttribute ? plainText(editor.getEditedPostAttribute('title')) : '';
      var parents = store.getBlockParents(props.clientId).slice().reverse();
      for (var i = 0; i < parents.length; i++) {
        var parent = store.getBlock(parents[i]);
        if (parent && parent.name === 'core/group') {
          var title = groupHeading(parent.innerBlocks || [], postTitle);
          if (title) return title;
        }
      }
      return postTitle || labels.fallback;
    }, [props.clientId]);
    var mode = attributes.displayMode || settings.display_mode;
    var options = (attributes.options || []).filter(function (item) { return item.trim(); });
    var blockProps = useBlockProps({ className: 'astrid-editor-preview' });
    var inspector = el(InspectorControls, {}, el(PanelBody, { title: 'Astrid', initialOpen: true },
      el('p', {}, el('strong', {}, labels.activity + ': '), activity),
      el('p', { className: 'components-base-control__help' }, labels.hint),
      el(SelectControl, {
        label: labels.display, value: attributes.displayMode,
        options: [
          { label: labels.default, value: '' }, { label: labels.popup, value: 'popup' },
          { label: labels.below, value: 'below' }, { label: labels.static, value: 'static' }
        ], onChange: function (value) { props.setAttributes({ displayMode: value }); }
      }),
      el(TextControl, {
        label: labels.button, value: attributes.buttonLabel, placeholder: settings.button_label,
        onChange: function (value) { props.setAttributes({ buttonLabel: value }); }
      }),
      multiple ? el(TextareaControl, {
        label: labels.options, help: labels.optionsHelp, value: (attributes.options || []).join('\n'),
        rows: 6, onChange: function (value) { props.setAttributes({ options: value.split('\n') }); }
      }) : null
    ));
    var choice = multiple ? el('div', { className: 'astrid-option-field' },
      el('label', {}, settings.option_label),
      el('select', { disabled: true, 'aria-label': settings.option_label },
        el('option', { value: '' }, settings.option_placeholder),
        options.map(function (option, index) { return el('option', { key: index, value: index }, option); })
      ), !options.length ? el(Notice, { status: 'warning', isDismissible: false }, labels.empty) : null
    ) : null;
    var button = el('div', { className: 'wp-block-buttons' }, el('div', { className: 'wp-block-button' },
      el('button', { type: 'button', className: 'wp-block-button__link wp-element-button', style: buttonStyle(), tabIndex: -1 }, attributes.buttonLabel || settings.button_label)
    ));
    var preview = el('div', { className: 'astrid-editor-form' },
      el('h3', {}, template(settings.form_title, activity)),
      el('p', {}, template(settings.form_intro, activity)),
      ['name', 'phone', 'email', 'message'].map(function (field) {
        return el('div', { key: field, className: 'astrid-field' },
          el('label', {}, settings[field + '_label']),
          el(field === 'message' ? 'textarea' : 'input', { disabled: true, 'aria-label': settings[field + '_label'], rows: field === 'message' ? 2 : undefined })
        );
      }),
      el('p', {}, settings.consent_text.replace('{terms}', settings.terms_label).replace('{privacy}', settings.privacy_label)),
      el('div', { className: 'wp-block-buttons' }, el('div', { className: 'wp-block-button' }, el('button', { type: 'button', className: 'wp-block-button__link wp-element-button', style: buttonStyle(), disabled: true }, settings.submit_label)))
    );
    return el(Fragment, {}, inspector, el('div', blockProps,
      el('p', { className: 'astrid-editor-context' }, el('strong', {}, 'Astrid · '), activity), choice,
      mode !== 'static' ? button : null,
      mode === 'static' ? preview : el('details', { className: 'astrid-editor-details' }, el('summary', {}, labels.preview), preview)
    ));
  }

  var shared = {
    buttonLabel: { type: 'string', default: '' }, displayMode: { type: 'string', default: '' }
  };
  ['registration', 'multiple-options'].forEach(function (type) {
    var multiple = type === 'multiple-options';
    wp.blocks.registerBlockType('astrid/' + type, {
      apiVersion: 3, title: multiple ? labels.multiple : labels.registration,
      category: 'widgets', icon: multiple ? 'list-view' : 'forms',
      description: labels.hint, keywords: ['activity', 'register', 'form', 'options'],
      attributes: Object.assign({}, shared, multiple ? { options: { type: 'array', default: [], items: { type: 'string' } } } : {}),
      usesContext: ['postId', 'postType'],
      supports: { html: false, anchor: true, spacing: { margin: true, padding: true } },
      edit: Edit, save: function () { return null; }
    });
  });
})(window.wp);
