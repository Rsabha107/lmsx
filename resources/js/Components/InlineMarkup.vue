<template>
  <template v-for="(node, i) in nodes" :key="i">
    <br v-if="node.tag === 'br'" />
    <component :is="node.tag" v-else-if="node.tag"><InlineMarkup :nodes="node.children" /></component>
    <template v-else>{{ node }}</template>
  </template>
</template>

<script setup>
import { computed } from 'vue';

defineOptions({ name: 'InlineMarkup' });

/**
 * Renders a message with <strong>, <b>, <em> and <br> only, through Vue nodes and
 * never v-html: any other tag, or a tag with attributes, stays visible text.
 */
const props = defineProps({
  text: { type: String, default: '' },
  // Internal: the already-parsed children of a nested tag.
  nodes: { type: Array, default: null },
});

const TAG = /<(\/?)(strong|b|em)>|<br\s*\/?>/gi;
const ENTITIES = {
  amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: '\u00a0',
  bull: '•', middot: '·', rarr: '→', larr: '←', mdash: '—', ndash: '–', hellip: '…', times: '×',
};

const decode = (s) => s.replace(/&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, (whole, code) => {
  if (code[0] === '#') {
    const n = code[1].toLowerCase() === 'x' ? parseInt(code.slice(2), 16) : parseInt(code.slice(1), 10);
    return Number.isFinite(n) && n > 0 && n <= 0x10ffff ? String.fromCodePoint(n) : whole;
  }
  return ENTITIES[code.toLowerCase()] ?? whole;
});

function parse(text) {
  const root = { children: [] };
  const stack = [root];
  let last = 0;

  for (const match of text.matchAll(TAG)) {
    const top = stack[stack.length - 1];
    if (match.index > last) top.children.push(decode(text.slice(last, match.index)));
    last = match.index + match[0].length;

    const tag = match[2]?.toLowerCase();
    if (!tag) {
      top.children.push({ tag: 'br' });
    } else if (!match[1]) {
      const node = { tag, children: [] };
      top.children.push(node);
      stack.push(node);
    } else if (stack.length > 1 && top.tag === tag) {
      stack.pop();
    }
  }

  if (last < text.length) stack[stack.length - 1].children.push(decode(text.slice(last)));

  return root.children;
}

const nodes = computed(() => props.nodes ?? parse(props.text ?? ''));
</script>
