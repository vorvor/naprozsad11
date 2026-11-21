(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.naprozsaPropertyPreview = {
    attach(context) {
      once('naprozsa-property-preview', '#elado-hazak .view-flats .views-row', context).forEach((tile) => {
        const image = tile.querySelector('.views-field-field-images img');
        const next = tile.querySelector('.views-field-field-images-1 img');
        if (!image || !next || !next.getAttribute('src')) return;

        const attributes = ['src', 'srcset', 'sizes', 'alt'];
        const original = Object.fromEntries(attributes.map((name) => [name, image.getAttribute(name)]));
        const preview = Object.fromEntries(attributes.map((name) => [name, next.getAttribute(name)]));
        const preload = new Image();
        let hovered = false;
        let focused = false;
        let ready = false;

        function apply(values) {
          attributes.forEach((name) => {
            if (values[name] === null) image.removeAttribute(name);
            else image.setAttribute(name, values[name]);
          });
        }

        function update() {
          if (hovered || focused) {
            if (ready) apply(preview);
            else {
              preload.onload = () => {
                ready = true;
                if (hovered || focused) apply(preview);
              };
              if (preview.srcset) preload.srcset = preview.srcset;
              if (preview.sizes) preload.sizes = preview.sizes;
              preload.src = preview.src;
            }
          } else {
            apply(original);
          }
        }

        tile.addEventListener('pointerenter', (event) => {
          if (event.pointerType === 'touch') return;
          hovered = true;
          update();
        });
        tile.addEventListener('pointerleave', (event) => {
          if (event.pointerType === 'touch') return;
          hovered = false;
          update();
        });
        tile.addEventListener('focusin', () => { focused = true; update(); });
        tile.addEventListener('focusout', (event) => {
          if (tile.contains(event.relatedTarget)) return;
          focused = false;
          update();
        });
      });
    },
  };
})(Drupal, once);
