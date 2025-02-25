<template>
  <div ref="collectionContainer" class="collection-container">
    <div v-for="element in elements" :key="element.id" class="collection-item">
      <div :is="element"></div>
    </div>

    <div class="d-flex">
      <editor-select
        ref="templateSelect"
        class="flex-grow-1 mr-2"
        :value="initialSelectValue"
        :name="templateSelectName"
        :options="templateSelectOptions"
        :allowempty="false"
      ></editor-select>
      <button
        class="btn btn-secondary"
        type="button"
        :disabled="!allowMore"
        @click="addCollectionItem"
      >
        <i class="fas fa-fw fa-plus"></i>
        {{ labels.add_collection_item }}
      </button>
    </div>
  </div>
</template>

<script>
import Vue from 'vue';
var uniqid = require('locutus/php/misc/uniqid');

export default {
  name: 'EditorCollection',
  props: {
    templates: {
      type: Array,
      required: true,
    },
    existingFields: {
      type: Array,
    },
    labels: {
      type: Object,
      required: true,
    },
    limit: {
      type: Number,
      required: true,
    },
  },
  data() {
    let elements = [];
    this.existingFields.forEach(function(field) {
      elements.push(Vue.compile(field.html));
    });

    let templateSelectOptions = [];

    this.templates.forEach(function(template) {
      templateSelectOptions.push({
        key: template.label,
        value: template.label,
      });
    });

    return {
      elements: elements,
      counter: elements.length,
      templateSelectName: 'templateSelect' + this.id,
      templateSelectOptions: templateSelectOptions,
      selector: {
        collectionContainer: '.collection-container',
        item: '.collection-item',
        remove: '.action-remove-collection-item',
        moveUp: '.action-move-up-collection-item',
        moveDown: '.action-move-down-collection-item',
      },
    };
  },
  computed: {
    initialSelectValue() {
      return this.templateSelectOptions[0].key;
    },
    allowMore: function() {
      return this.counter < this.limit;
    },
  },
  mounted() {
    let vueThis = this;
    /*
     ** Event listeners on collection items buttons
     ** This is a jQuery event listener, because Vue cannot handle an event emitted by a non-vue element.
     ** The collection items are not Vue elements in order to initialise them correctly within their twig template.
     */
    window.$(document).on('click', vueThis.selector.remove, function(e) {
      e.preventDefault();
      let collectionContainer = window
        .$(this)
        .closest(vueThis.selector.collectionContainer);
      vueThis.getCollectionItemFromPressedButton(this).remove();
      vueThis.setAllButtonsStates(collectionContainer);
      vueThis.counter--;
    });

    window.$(document).on('click', vueThis.selector.moveUp, function(e) {
      e.preventDefault();
      let thisCollectionItem = vueThis.getCollectionItemFromPressedButton(this);
      let prevCollectionitem = vueThis.getPreviousCollectionItem(
        thisCollectionItem,
      );
      window.$(thisCollectionItem).after(prevCollectionitem);

      vueThis.setButtonsState(thisCollectionItem);
      vueThis.setButtonsState(prevCollectionitem);
    });

    window.$(document).on('click', vueThis.selector.moveDown, function(e) {
      e.preventDefault();
      let thisCollectionItem = vueThis.getCollectionItemFromPressedButton(this);
      let nextCollectionItem = vueThis.getNextCollectionItem(
        thisCollectionItem,
      );
      window.$(thisCollectionItem).before(nextCollectionItem);

      vueThis.setButtonsState(thisCollectionItem);
      vueThis.setButtonsState(nextCollectionItem);
    });
  },
  updated() {
    this.setAllButtonsStates(window.$(this.$refs.collectionContainer));
  },
  methods: {
    setAllButtonsStates(collectionContainer) {
      let vueThis = this;
      collectionContainer.children(vueThis.selector.item).each(function() {
        vueThis.setButtonsState(window.$(this));
      });
    },
    setButtonsState(item) {
      //by default, enable
      item
        .find(this.selector.moveUp)
        .first()
        .removeAttr('disabled');
      item
        .find(this.selector.moveDown)
        .first()
        .removeAttr('disabled');

      if (!this.getPreviousCollectionItem(item)) {
        // first in collection
        item
          .find(this.selector.moveUp)
          .first()
          .attr('disabled', 'disabled');
      }

      if (!this.getNextCollectionItem(item)) {
        // last in collection
        item
          .find(this.selector.moveDown)
          .first()
          .attr('disabled', 'disabled');
      }
    },
    getPreviousCollectionItem(item) {
      return item.prev('.collection-item').length === 0
        ? false
        : item.prev('.collection-item');
    },
    getNextCollectionItem(item) {
      return item.next('.collection-item').length === 0
        ? false
        : item.next('.collection-item');
    },
    getCollectionItemFromPressedButton(button) {
      return window
        .$(button)
        .closest('.collection-item')
        .last();
    },
    addCollectionItem() {
      let template = this.getSelectedTemplate();

      let html = template.html.replace(
        '[' + new RegExp(template.hash, 'g') + ']',
        '[' + uniqid() + ']',
      );
      let res = Vue.compile(html);
      this.elements.push(res);
      this.counter++;
    },
    getSelectedTemplate() {
      let selectValue = this.$refs.templateSelect.selected;
      if (Array.isArray(selectValue)) {
        selectValue = selectValue[0];
      }

      return this.templates.find(
        template => template.label === selectValue.key,
      );
    },
  },
};
</script>
