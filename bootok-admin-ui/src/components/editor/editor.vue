<template>
  <div style="border: 1px solid var(--color-border-2); width: 100%">
    <Toolbar
      style="border-bottom: 1px solid var(--color-border-2)"
      :default-config="toolbarConfig"
      :editor="editorRef"
      :mode="mode"
    />
    <Editor
      style="height: 400px; overflow-y: hidden"
      :default-config="editorConfig"
      :mode="mode"
      v-model="content"
      @on-created="handleCreated"
    />
  </div>
</template>

<script lang="ts" setup>
  import { onBeforeUnmount, shallowRef } from 'vue';
  import '@wangeditor/editor/dist/css/style.css';
  import { Editor, Toolbar } from '@wangeditor/editor-for-vue';
  import { getToken } from '@/utils/auth';

  const props = defineProps({
    mode: {
      type: String,
      default: 'default', // default 或 simple
    },
    uploadServer: {
      type: String,
      required: true,
    },
  });

  const content = defineModel<string>('content');
  // 编辑器实例，必须用 shallowRef
  const editorRef = shallowRef();
  const toolbarConfig = {};

  const editorConfig = {
    placeholder: '请输入内容...',
    MENU_CONF: {
      uploadImage: {
        server: props.uploadServer,
        fieldName: 'file',
        maxNumberOfFiles: 10,
        allowedFileTypes: ['image/*'],
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
      },
      uploadVideo: {
        server: props.uploadServer,
        fieldName: 'file',
        maxFileSize: 20 * 1024 * 1024,
        maxNumberOfFiles: 1,
        allowedFileTypes: ['video/*'],
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
      },
    },
  };

  onBeforeUnmount(() => {
    const editor = editorRef.value;
    if (editor == null) return;
    editor.destroy();
  });

  function handleCreated(editor: any) {
    editorRef.value = editor;
  }
</script>
