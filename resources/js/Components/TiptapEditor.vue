<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { Editor, EditorContent } from '@tiptap/vue-3'
import { Node, mergeAttributes } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Image from '@tiptap/extension-image'
import Link from '@tiptap/extension-link'
import Underline from '@tiptap/extension-underline'
import TextAlign from '@tiptap/extension-text-align'
import TaskList from '@tiptap/extension-task-list'
import TaskItem from '@tiptap/extension-task-item'
import CodeBlock from '@tiptap/extension-code-block'
import HorizontalRule from '@tiptap/extension-horizontal-rule'
import Subscript from '@tiptap/extension-subscript'
import Superscript from '@tiptap/extension-superscript'
import Highlight from '@tiptap/extension-highlight'
import { TextStyle } from '@tiptap/extension-text-style'
import { Color } from '@tiptap/extension-color'
import { Table } from '@tiptap/extension-table'
import { Plugin, PluginKey } from '@tiptap/pm/state'
import { TableRow as BaseTableRow } from '@tiptap/extension-table-row'
import { TableCell as BaseTableCell } from '@tiptap/extension-table-cell'
import { TableHeader as BaseTableHeader } from '@tiptap/extension-table-header'
import TableInsertModal from './TableInsertModal.vue'
import CtaBlockModal from './CtaBlockModal.vue'
import { CtaBlock, InternalLinkBlock } from './TiptapCtaBlock.js'
import { TextSelection } from '@tiptap/pm/state'


const ImageCaption = Node.create({
  name: 'imageCaption',
  group: 'block',
  content: 'inline*',
  priority: 1000, // ← Высокий приоритет, чтобы парсился раньше обычного <p>
  parseHTML() {
    return [
      {
        tag: 'p[class="blog-image-caption"]',
        priority: 1000,
      },
      {
        tag: 'p.blog-image-caption',
        priority: 1000,
      },
    ]
  },
  renderHTML({ HTMLAttributes }) {
    return ['p', mergeAttributes(HTMLAttributes, { class: 'blog-image-caption' }), 0]
  },
})
const KeyInsight = Node.create({
  name: 'keyInsight',
  group: 'block',
  content: 'block',
  parseHTML() {
    return [
      { tag: 'div.key-insight' },
    ]
  },
  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, { class: 'key-insight' }), 0]
  },
})
const BlogIndent = Node.create({
  name: 'blogIndent',
  group: 'block',
  content: 'inline*',
  priority: 1000, // ← Высокий приоритет
  parseHTML() {
    return [
      {
        tag: 'p.blog-indent',
        priority: 1000, // ← И здесь
      },
    ]
  },
  renderHTML({ HTMLAttributes }) {
    return ['p', mergeAttributes(HTMLAttributes, { class: 'blog-indent' }), 0]
  },
})
const insertIndent = () => {
  if (!editor.value) return
  editor.value.chain()
      .focus()
      .insertContent('<p class="blog-indent">&nbsp;</p>')
      .run()
}
const TableCell = BaseTableCell.extend({
  renderHTML({ node, HTMLAttributes }) {
    const colwidth = node.attrs.colwidth
    const attrs = mergeAttributes(HTMLAttributes, this.options.HTMLAttributes)
    if (colwidth && colwidth.length) {
      const totalWidth = colwidth.reduce((sum, w) => sum + (w || 0), 0)
      if (totalWidth > 0) {
        attrs.style = (attrs.style || '') + `width: ${totalWidth}px;`
      }
    }
    return ['td', attrs, 0]
  }
})


const TableHeader = BaseTableHeader.extend({
  renderHTML({ node, HTMLAttributes }) {
    const colwidth = node.attrs.colwidth
    const attrs = mergeAttributes(HTMLAttributes, this.options.HTMLAttributes)
    if (colwidth && colwidth.length) {
      const totalWidth = colwidth.reduce((sum, w) => sum + (w || 0), 0)
      if (totalWidth > 0) {
        attrs.style = (attrs.style || '') + `width: ${totalWidth}px;`
      }
    }
    return ['th', attrs, 0]
  }
})

const TableRow = BaseTableRow.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      rowHeight: {
        default: null,
        parseHTML: (element) => {
          const height = element.getAttribute('data-row-height') || element.style.height
          return height ? parseInt(height) : null
        },
        renderHTML: (attributes) => {
          if (!attributes.rowHeight) return {}
          return {
            'data-row-height': attributes.rowHeight,
            style: `height: ${attributes.rowHeight}px;`,
          }
        },
      },
    }
  },
})

const showTableModal = ref(false)
const showCtaModal = ref(false)


const insertTableWithConfig = (config) => {
  editor.value.chain().focus().insertTable(config).run()
}


const insertCtaBlock = (data) => {
  if (!editor.value) return
  editor.value.chain()
      .focus()
      .insertContent({
        type: 'ctaBlock',
        attrs: {
          title: data.title || '',
          description: data.description || '',
          buttonText: data.buttonText || '',
          buttonUrl: data.buttonUrl || ''
        }
      })
      .run()
}


const normalizePastedLinks = (root) => {
  root.querySelectorAll('a[href]').forEach((anchor) => {
    anchor.removeAttribute('contenteditable')
    anchor.removeAttribute('onclick')
    anchor.removeAttribute('onmousedown')

    while (anchor.childElementCount === 1 && anchor.firstElementChild?.tagName === 'SPAN') {
      const span = anchor.firstElementChild
      if (span.querySelector('a, img, br, table')) break
      while (span.firstChild) {
        anchor.insertBefore(span.firstChild, span)
      }
      anchor.removeChild(span)
    }
  })
}

const insertLink = () => {
  if (!editor.value) return

  if (editor.value.isActive('link')) {
    editor.value.chain().focus().extendMarkRange('link').run()
  }

  const previousUrl = editor.value.getAttributes('link').href
  const { from, to, empty } = editor.value.state.selection
  const selectedText = empty ? '' : editor.value.state.doc.textBetween(from, to, ' ')

  if (previousUrl) {
    const url = prompt(
      'URL ссылки (пусто — убрать ссылку, текст останется):',
      previousUrl,
    )
    if (url === null) return

    if (!url.trim()) {
      editor.value.chain().focus().extendMarkRange('link').unsetLink().run()
      return
    }

    editor.value
      .chain()
      .focus()
      .extendMarkRange('link')
      .setLink({
        href: url.trim(),
        target: '_blank',
        rel: 'noopener noreferrer',
      })
      .run()
    return
  }

  const text = prompt('Введите текст ссылки:', selectedText.trim() || '')
  if (text === null || !text.trim()) return

  const url = prompt('Введите URL ссылки (например, https://example.com):')
  if (url === null || !url.trim()) return

  if (empty) {
    editor.value
      .chain()
      .focus()
      .insertContent({
        type: 'text',
        text: text.trim(),
        marks: [
          {
            type: 'link',
            attrs: {
              href: url.trim(),
              target: '_blank',
              rel: 'noopener noreferrer',
            },
          },
        ],
      })
      .command(({ tr }) => {
        tr.setStoredMarks([])
        return true
      })
      .run()
    return
  }

  editor.value
    .chain()
    .focus()
    .setLink({
      href: url.trim(),
      target: '_blank',
      rel: 'noopener noreferrer',
    })
    .run()
}

const props = defineProps({
  modelValue: { type: String, default: '' }
})
const emit = defineEmits(['update:modelValue'])

const editor = ref(null)

const getCsrfToken = () => {
  const meta = document.querySelector('meta[name="csrf-token"]')
  if (meta?.content) return meta.content
  const cookies = document.cookie.split(';')
  const xsrf = cookies.find(c => c.trim().startsWith('XSRF-TOKEN='))
  if (xsrf) return decodeURIComponent(xsrf.split('=')[1])
  return ''
}

const convertToWebP = (file, maxWidth, maxHeight) => {
  return new Promise((resolve) => {
    const img = new window.Image()
    const reader = new FileReader()
    reader.onload = (e) => {
      img.onload = () => {
        let width = img.width
        let height = img.height
        if (width > maxWidth || height > maxHeight) {
          const ratio = Math.min(maxWidth / width, maxHeight / height)
          width = Math.round(width * ratio)
          height = Math.round(height * ratio)
        }
        const canvas = document.createElement('canvas')
        canvas.width = width
        canvas.height = height
        const ctx = canvas.getContext('2d')
        ctx.drawImage(img, 0, 0, width, height)
        canvas.toBlob((blob) => {
          if (blob) {
            const webpFile = new File([blob], file.name.replace(/\.[^.]+$/, '') + '.webp', { type: 'image/webp', lastModified: Date.now() })
            resolve(webpFile)
          } else {
            resolve(file)
          }
        }, 'image/webp', 0.85)
      }
      img.onerror = () => resolve(file)
      img.src = e.target.result
    }
    reader.onerror = () => resolve(file)
    reader.readAsDataURL(file)
  })
}

const uploadImage = async (file) => {
  try {
    let processedFile = file
    const isGif = file.type === 'image/gif' || /\.gif$/i.test(file.name || '')
    if (file.type !== 'image/webp' && !isGif) {
      processedFile = await convertToWebP(file, 883, 572)
    }
    const formData = new FormData()
    formData.append('file', processedFile)
    const token = getCsrfToken()
    const response = await fetch('/admin/upload-image', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': token,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      body: formData,
      credentials: 'include'
    })
    if (!response.ok) {
      const errorText = await response.text()
      throw new Error(`Ошибка загрузки: ${response.status} ${errorText}`)
    }
    const data = await response.json()
    if (!data.url) throw new Error('Сервер не вернул URL картинки')
    let url = data.url
    if (url.startsWith('/')) url = window.location.origin + url
    return url
  } catch (e) {
    console.error('Upload error:', e)
    alert(e.message || 'Не удалось загрузить картинку')
    return null
  }
}

const handleImageUpload = async (event) => {
  const file = event.target.files?.[0]
  if (!file || !editor.value) return
  const url = await uploadImage(file)
  if (url) editor.value.chain().focus().setImage({ src: url }).run()
  event.target.value = ''
}

const insertImageCaption = () => {
  if (!editor.value) return

  const isInTable = editor.value.isActive('table')

  if (isInTable) {
    const { state } = editor.value
    const { $from } = state.selection

    let tableNodeEnd = null
    for (let depth = $from.depth; depth > 0; depth--) {
      const node = $from.node(depth)
      if (node.type.name === 'table') {
        tableNodeEnd = $from.before(depth) + node.nodeSize
        break
      }
    }

    if (tableNodeEnd !== null) {
      editor.value.chain()
          .focus()
          .insertContentAt(tableNodeEnd, {
            type: 'imageCaption',
            content: [{ type: 'text', text: 'Подпись к таблице' }]
          })
          .run()
    }
  } else {
    const { from, to, empty } = editor.value.state.selection
    if (!empty) {
      const selectedText = editor.value.state.doc.textBetween(from, to)
      editor.value.chain().focus().deleteRange({ from, to })
          .insertContent({
            type: 'imageCaption',
            content: [{ type: 'text', text: selectedText }]
          }).run()
    } else {
      editor.value.chain().focus()
          .insertContent({
            type: 'imageCaption',
            content: [{ type: 'text', text: 'Подпись' }]
          }).run()
      setTimeout(() => {
        const { state } = editor.value
        let targetPos = null
        let targetSize = 0
        state.doc.descendants((node, pos) => {
          if (node.type.name === 'imageCaption') {
            targetPos = pos
            targetSize = node.nodeSize
          }
        })
        if (targetPos !== null) {
          editor.value.chain().focus()
              .setTextSelection({ from: targetPos + 1, to: targetPos + targetSize - 1 })
              .run()
        }
      }, 50)
    }
  }
}

const insertQuote = () => {
  const text = prompt('Введите текст цитаты:')

  // Если пользователь нажал "Отмена" или оставил пустым — выходим
  if (text === null || !text.trim()) return

  const author = prompt('Введите автора цитаты (необязательно):')
  // author может быть null или пустым — это нормально, цитата без автора допустима

  let html = `<blockquote><p>${text}</p>`
  if (author && author.trim()) {
    html += `<cite>${author}</cite>`
  }
  html += `</blockquote>`

  editor.value.chain()
      .focus()
      .insertContent(html)
      .run()
}


const insertKeyInsight = () => {
  if (!editor.value) return
  const { from, to, empty } = editor.value.state.selection

  if (!empty) {

    const selectedText = editor.value.state.doc.textBetween(from, to)
    editor.value.chain()
        .focus()
        .deleteRange({ from, to })
        .insertContent({
          type: 'keyInsight',
          content: [{
            type: 'heading',
            attrs: { level: 4 },
            content: [{ type: 'text', text: selectedText }]
          }]
        })
        .run()
  } else {

    editor.value.chain()
        .focus()
        .insertContent({
          type: 'keyInsight',
          content: [{
            type: 'heading',
            attrs: { level: 4 },
            content: [{ type: 'text', text: 'Ключевая мысль' }]
          }]
        })
        .run()

    setTimeout(() => {
      const { state } = editor.value
      let targetPos = null
      let targetSize = 0
      state.doc.descendants((node, pos) => {
        if (node.type.name === 'keyInsight') {
          targetPos = pos + 1
          targetSize = node.nodeSize - 2
        }
      })
      if (targetPos !== null) {
        editor.value.chain()
            .focus()
            .setTextSelection({ from: targetPos, to: targetPos + targetSize })
            .run()
      }
    }, 50)
  }
}
const removeHeading = () => {
  if (!editor.value) return
  editor.value.chain().focus().setParagraph().run()
}


const setTextColor = (color) => {
  if (!editor.value) return
  if (color) {
    editor.value.chain().focus().setColor(color).run()
  } else {
    editor.value.chain().focus().unsetColor().run()
  }
}


const setHighlightColor = (color) => {
  if (!editor.value) return
  if (color) {
    editor.value.chain().focus().toggleHighlight({ color }).run()
  } else {
    editor.value.chain().focus().unsetHighlight().run()
  }
}
const rowResizePluginKey = new PluginKey('rowResize')

const createRowResizePlugin = () => {
  let dragging = null
  let hoverRow = null
  let indicator = null

  const createIndicator = () => {
    if (!indicator) {
      indicator = document.createElement('div')
      indicator.style.cssText = `
        position: fixed;
        height: 3px;
        background: #3b82f6;
        pointer-events: none;
        z-index: 9999;
        display: none;
      `
      document.body.appendChild(indicator)
    }
    return indicator
  }

  const findRowAtY = (clientX, clientY) => {
    const rows = Array.from(document.querySelectorAll('.ProseMirror table tr'))

    for (const row of rows) {
      const rect = row.getBoundingClientRect()

      if (clientX < rect.left || clientX > rect.right) continue

      const distanceFromBottom = rect.bottom - clientY
      if (distanceFromBottom >= -5 && distanceFromBottom <= 10) {
        return row
      }
    }
    return null
  }

  // Находим позицию строки в документе ProseMirror
  const findRowPosition = (view, rowElement) => {
    const { state } = view
    let foundPos = null

    state.doc.descendants((node, pos) => {
      if (foundPos !== null) return false // уже нашли

      if (node.type.name === 'tableRow') {
        const dom = view.nodeDOM(pos)
        if (dom === rowElement) {
          foundPos = pos
          return false
        }
      }
    })

    return foundPos
  }

  return new Plugin({
    key: rowResizePluginKey,
    props: {
      handleDOMEvents: {
        mousemove: (view, event) => {
          const ind = createIndicator()

          if (dragging) {
            const deltaY = event.clientY - dragging.startY
            const newHeight = Math.max(30, dragging.startHeight + deltaY)
            dragging.row.style.height = `${newHeight}px`
            dragging.currentHeight = newHeight

            const rect = dragging.row.getBoundingClientRect()
            ind.style.left = `${rect.left}px`
            ind.style.top = `${rect.bottom - 1}px`
            ind.style.width = `${rect.width}px`
            ind.style.display = 'block'

            event.preventDefault()
            return true
          }

          const row = findRowAtY(event.clientX, event.clientY)

          if (row !== hoverRow) {
            if (hoverRow) {
              hoverRow.classList.remove('row-hover-resize')
            }
            if (row) {
              row.classList.add('row-hover-resize')

              const rect = row.getBoundingClientRect()
              ind.style.left = `${rect.left}px`
              ind.style.top = `${rect.bottom - 1}px`
              ind.style.width = `${rect.width}px`
              ind.style.display = 'block'
            } else {
              ind.style.display = 'none'
            }
            hoverRow = row
          }

          return false
        },

        mousedown: (view, event) => {
          if (event.button !== 0) return false

          const row = findRowAtY(event.clientX, event.clientY)

          if (row) {
            // 🔹 Находим позицию строки в документе СЕЙЧАС
            const rowPos = findRowPosition(view, row)

            if (rowPos !== null) {
              dragging = {
                row: row,
                rowPos: rowPos, // 🔹 Сохраняем позицию
                startY: event.clientY,
                startHeight: row.offsetHeight,
                currentHeight: row.offsetHeight,
                view: view,
              }

              document.body.classList.add('row-resizing')
              row.classList.add('row-dragging')

              event.preventDefault()
              return true
            }
          }

          return false
        },

        mouseup: (view, event) => {
          const ind = createIndicator()
          ind.style.display = 'none'

          if (!dragging) return false

          const row = dragging.row
          const finalHeight = Math.round(dragging.currentHeight || row.offsetHeight)
          const rowPos = dragging.rowPos // 🔹 Используем сохранённую позицию

          // Убираем визуальные эффекты
          document.body.classList.remove('row-resizing')
          row.classList.remove('row-dragging')

          // 🔹 Сохраняем высоту через ProseMirror
          if (finalHeight >= 30 && rowPos !== null) {
            const { state, dispatch } = dragging.view
            const node = state.doc.nodeAt(rowPos)

            if (node && node.type.name === 'tableRow') {
              const tr = state.tr.setNodeMarkup(rowPos, null, {
                ...node.attrs,
                rowHeight: finalHeight,
              })
              dispatch(tr)
            }
          }

          dragging = null
          event.preventDefault()
          return true
        },

        mouseleave: (view, event) => {
          const ind = createIndicator()
          ind.style.display = 'none'

          if (dragging) {
            document.body.classList.remove('row-resizing')
            dragging.row.classList.remove('row-dragging')
            dragging = null
          }
          if (hoverRow) {
            hoverRow.classList.remove('row-hover-resize')
            hoverRow = null
          }
        },
      },
    },
  })
}
onMounted(() => {
  editor.value = new Editor({
    content: props.modelValue,
    editorProps: {
      handleDOMEvents: {
        click: (view, event) => {
          const target = event.target
          if (!(target instanceof Element)) return false
          const anchor = target.closest('a')
          if (anchor && view.dom.contains(anchor)) {
            event.preventDefault()
          }
          return false
        },
      },
      transformPastedHTML: (html) => {
        const div = document.createElement('div')
        div.innerHTML = html

        normalizePastedLinks(div)

        // Удаляем цвет со всех элементов
        div.querySelectorAll('*').forEach(el => {
          // Убираем inline style color
          el.style.removeProperty('color')
          el.style.removeProperty('background-color')
          el.style.removeProperty('background')

          // Убираем атрибут color (старый HTML)
          el.removeAttribute('color')
          el.removeAttribute('bgcolor')

          // Если style стал пустым — удаляем его полностью
          if (!el.getAttribute('style')?.trim()) {
            el.removeAttribute('style')
          }
        })

        return div.innerHTML
      },
      plugins: [createRowResizePlugin()],
    },
    extensions: [
      StarterKit.configure({
        heading: { levels: [2, 3, 4, 5] },
        codeBlock: false,
        link: false,
      }),

      Underline,
      Subscript,
      Superscript,
      Highlight.configure({ multicolor: true }),
      TextStyle,
      Color,
      KeyInsight,
      BlogIndent,
      TextAlign.configure({
        types: ['heading', 'paragraph', 'tableCell', 'tableHeader'],
      }),

      TaskList,
      TaskItem.configure({
        nested: true,
      }),

      CodeBlock.configure({
        HTMLAttributes: { class: 'code-block' },
      }),

      HorizontalRule,
      Link.configure({
        openOnClick: false,
        enableClickSelection: true,
        HTMLAttributes: {
          rel: 'noopener noreferrer',
          target: '_blank',
        },
      }),
      Image.configure({ inline: false, HTMLAttributes: { class: 'blog-image' } }),
      ImageCaption,
      KeyInsight,

      CtaBlock,
      InternalLinkBlock,

      Table.configure({ resizable: true }),
      TableRow,
      TableHeader,
      TableCell,
    ],
    onUpdate: ({ editor }) => {
      emit('update:modelValue', editor.getHTML())
    },
  })
})

watch(() => props.modelValue, (value) => {
  const isSame = editor.value?.getHTML() === value
  if (isSame) return
  editor.value?.commands.setContent(value, false)
})

onBeforeUnmount(() => {
  editor.value?.destroy()
})
const getActiveHeading = () => {
  if (!editor.value) return ''
  if (editor.value.isActive('heading', { level: 2 })) return '2'
  if (editor.value.isActive('heading', { level: 3 })) return '3'
  if (editor.value.isActive('heading', { level: 4 })) return '4'
  if (editor.value.isActive('heading', { level: 5 })) return '5'
  return ''
}
const setHeading = (level) => {
  if (!editor.value) return

  if (level === 'remove' || level === false || level === '') {
    editor.value.chain().focus().setParagraph().run()
    return
  }

  const lvl = parseInt(level)
  const { state, view } = editor.value
  const { from, to, empty } = state.selection


  if (empty) {
    editor.value.chain().focus().setNode('heading', { level: lvl }).run()
    return
  }


  const selectedText = state.doc.textBetween(from, to)
  if (!selectedText.trim()) {
    editor.value.chain().focus().setNode('heading', { level: lvl }).run()
    return
  }


  const $from = state.doc.resolve(from)
  const $to = state.doc.resolve(to)
  const blockStart = $from.start()
  const blockEnd = $to.end()


  const textBefore = from > blockStart ? state.doc.textBetween(blockStart, from) : ''
  const textAfter = to < blockEnd ? state.doc.textBetween(to, blockEnd) : ''


  const { tr, schema } = state


  tr.delete(blockStart, blockEnd)


  const nodes = []


  if (textBefore && textBefore.trim().length > 0) {
    nodes.push(schema.nodes.paragraph.create(null, schema.text(textBefore)))
  }


  nodes.push(schema.nodes.heading.create({ level: lvl }, schema.text(selectedText)))


  if (textAfter && textAfter.trim().length > 0) {
    nodes.push(schema.nodes.paragraph.create(null, schema.text(textAfter)))
  }


  tr.insert(blockStart, nodes)


  view.dispatch(tr)


  editor.value.commands.focus()
}
</script>

<template>
  <div class="border rounded-lg overflow-hidden bg-white" style="overflow: auto; max-height: 500px" >
    <div v-if="editor" class="sticky top-0 z-20 bg-white border-b shadow-sm" style="position: sticky;">
      <div class="flex flex-wrap gap-1 p-2">
      <!-- 🔹 Форматирование текста -->
      <div class="flex gap-1 border-r border-gray-300 pr-2 mr-1">
        <button type="button" @click="editor.chain().focus().toggleBold().run()"
                :class="{ 'bg-blue-100 text-blue-700': editor.isActive('bold') }"
                class="px-2 py-1 text-sm rounded hover:bg-gray-200 font-bold" title="Жирный">B</button>
        <button type="button" @click="editor.chain().focus().toggleItalic().run()"
                :class="{ 'bg-blue-100 text-blue-700': editor.isActive('italic') }"
                class="px-2 py-1 text-sm rounded hover:bg-gray-200 italic" title="Курсив">I</button>
        <button type="button" @click="editor.chain().focus().toggleUnderline().run()"
                :class="{ 'bg-blue-100 text-blue-700': editor.isActive('underline') }"
                class="px-2 py-1 text-sm rounded hover:bg-gray-200 underline" title="Подчёркивание">U</button>
        <button type="button" @click="editor.chain().focus().toggleStrike().run()"
                :class="{ 'bg-blue-100 text-blue-700': editor.isActive('strike') }"
                class="px-2 py-1 text-sm rounded hover:bg-gray-200 line-through" title="Зачёркивание">S</button>
        <button type="button" @click="editor.chain().focus().toggleSubscript().run()"
                :class="{ 'bg-blue-100 text-blue-700': editor.isActive('subscript') }"
                class="px-2 py-1 text-sm rounded hover:bg-gray-200" title="Подстрочный">X₂</button>
        <button type="button" @click="editor.chain().focus().toggleSuperscript().run()"
                :class="{ 'bg-blue-100 text-blue-700': editor.isActive('superscript') }"
                class="px-2 py-1 text-sm rounded hover:bg-gray-200" title="Надстрочный">X²</button>
      </div>





      <div class="relative border-r border-gray-300 pr-2 mr-1">
        <select
            @change="setHeading($event.target.value); $event.target.value = ''"
            :value="getActiveHeading()"
            class="text-sm border border-gray-300 rounded px-2 py-1 bg-white hover:bg-gray-50"
        >
          <option value="" disabled>Заголовок</option>
          <option value="remove">Убрать заголовок</option>
          <option value="2">H2 — Раздел</option>
          <option value="3">H3 — Подраздел</option>
          <option value="4">H4 — Деталь</option>
          <option value="5">H5 — Мелкий</option>
        </select>
      </div>


      <div class="flex gap-1 border-r border-gray-300 mr-1">
        <button type="button" @click="editor.chain().focus().setTextAlign('left').run()"
                :class="{ 'bg-blue-100 text-blue-700': editor.isActive({ textAlign: 'left' }) }"
                class="px-2 py-1 text-sm rounded hover:bg-gray-200" title="По левому">⬅️</button>
        <button type="button" @click="editor.chain().focus().setTextAlign('center').run()"
                :class="{ 'bg-blue-100 text-blue-700': editor.isActive({ textAlign: 'center' }) }"
                class="px-2 py-1 text-sm rounded hover:bg-gray-200" title="По центру">⬆️</button>
        <button type="button" @click="editor.chain().focus().setTextAlign('right').run()"
                :class="{ 'bg-blue-100 text-blue-700': editor.isActive({ textAlign: 'right' }) }"
                class="px-2 py-1 text-sm rounded hover:bg-gray-200" title="По правому">➡️</button>
        <button type="button" @click="editor.chain().focus().setTextAlign('justify').run()"
                :class="{ 'bg-blue-100 text-blue-700': editor.isActive({ textAlign: 'justify' }) }"
                class="px-2 py-1 text-sm rounded hover:bg-gray-200" title="По ширине">☰</button>
      </div>

      <!-- 🔹 Списки -->
      <div class="flex gap-1 border-r border-gray-300 mr-1">
        <button type="button" @click="editor.chain().focus().toggleBulletList().run()"
                :class="{ 'bg-blue-100 text-blue-700': editor.isActive('bulletList') }"
                class="px-2 py-1 text-sm rounded hover:bg-gray-200" title="Маркированный список">• Список</button>
        <button type="button" @click="editor.chain().focus().toggleOrderedList().run()"
                :class="{ 'bg-blue-100 text-blue-700': editor.isActive('orderedList') }"
                class="px-2 py-1 text-sm rounded hover:bg-gray-200" title="Нумерованный список">1. Список</button>
        <button type="button" @click="editor.chain().focus().toggleTaskList().run()"
                :class="{ 'bg-blue-100 text-blue-700': editor.isActive('taskList') }"
                class="px-2 py-1 text-sm rounded hover:bg-gray-200" title="Список задач">☑ Задачи</button>
      </div>



        <div class="flex gap-1 border-gray-300">
          <label class="px-2 py-1 text-sm rounded hover:bg-gray-200 cursor-pointer" title="Загрузить картинку">
            🖼️ Картинка
            <input type="file" accept="image/*" @change="handleImageUpload" class="hidden" />
          </label>
          <button type="button" @click="insertImageCaption()" class="px-2 py-1 text-sm rounded hover:bg-gray-200" title="Подпись к картинке">📝 Подпись</button>
          <button type="button" @click="insertQuote()" class="px-2 py-1 text-sm rounded hover:bg-gray-200" title="Цитата">💬 Цитата</button>
          <button
              type="button"
              @click="insertKeyInsight()"
              :class="{ 'bg-amber-100 text-amber-700': editor.isActive('keyInsight') }"
              class="px-2 py-1 text-sm rounded hover:bg-gray-200"
              title="Ключевая мысль"
          >
            💡 Ключевая мысль
          </button>
          <button type="button" @click="showTableModal = true" class="px-2 py-1 text-sm rounded hover:bg-gray-200" title="Таблица">📊 Таблица</button>
          <button type="button" @click="editor.chain().focus().toggleHeaderColumn().run()"
                  :class="{ 'bg-blue-100 text-blue-700': editor.isActive('tableHeader') }"
                  class="px-2 py-1 text-sm rounded hover:bg-gray-200" title="Заголовочный столбец">Загл. столбец</button>
          <button type="button" @click="showCtaModal = true" class="px-2 py-1 text-sm rounded hover:bg-blue-100 text-blue-700" title="CTA-блок">⭐ CTA</button>
          <button
              type="button"
              @click="insertLink()"
              :class="{ 'bg-blue-100 text-blue-700': editor.isActive('link') }"
              class="px-2 py-1 text-sm rounded hover:bg-gray-200"
              :title="editor.isActive('link') ? 'Изменить или убрать ссылку' : 'Вставить ссылку'"
          >🔗 Ссылка</button>
          <button type="button" @click="insertIndent()" class="px-2 py-1 text-sm rounded hover:bg-gray-200" title="Отступ">Отступ</button>
        </div>
    </div>
    </div>
    <div>
      <EditorContent :editor="editor" class="prose prose-sm max-w-none p-4 min-h-[250px] focus:outline-none" />
    </div>

    <TableInsertModal v-model="showTableModal" @insert="insertTableWithConfig" />
    <CtaBlockModal v-model="showCtaModal" @insert="insertCtaBlock" />
  </div>
</template>

<style>

.ProseMirror:focus { outline: none; }
.ProseMirror p.is-editor-empty:first-child::before {
  content: 'Начните писать...';
  color: #9ca3af;
  float: left;
  pointer-events: none;
  height: 0;
}


.blog-content > * {
  margin-top: 0;
  margin-bottom: 0;
}


.blog-content h2,
.ProseMirror h2 {
  margin-top: 48px;
  margin-bottom: 0;
}


.blog-content h3,
.ProseMirror h3 {
  margin-top: 32px;
  margin-bottom: 8px;
}


.blog-content h4,
.ProseMirror h4 {
  margin-top: 21px;
  margin-bottom: 6px;
}


.blog-content h5,
.ProseMirror h5 {
  margin-top: 16px;
  margin-bottom: 6px;
}


.blog-content p,
.ProseMirror p {
  margin-top: 12px;
  margin-bottom: 12px;
}


.blog-content blockquote,
.ProseMirror blockquote {
  margin-top: 32px;
  margin-bottom: 32px;
}


.blog-content .blog-image,
.ProseMirror .blog-image {
  margin-top: 24px;
  margin-bottom: 48px;
}

.blog-content .blog-image:has(+ .blog-image-caption),
.ProseMirror .blog-image:has(+ .blog-image-caption) {
  margin-bottom: 12px;
}

.blog-content .blog-image-caption,
.ProseMirror .blog-image-caption {
  margin-top: 0;
  margin-bottom: 48px;
}

.blog-content table,
.ProseMirror table {
  margin-top: 32px;
  margin-bottom: 48px;
}

.blog-content ul,
.blog-content ol,
.ProseMirror ul:not([data-type="taskList"]),
.ProseMirror ol {
  margin-top: 12px;
  margin-bottom: 12px;
}
.blog-content ul li,
.blog-content ol li,
.ProseMirror ul:not([data-type="taskList"]) li,
.ProseMirror ol li {
  margin-top: 0;
  margin-bottom: 12px;
}
.blog-content ul li:last-child,
.blog-content ol li:last-child,
.ProseMirror ul:not([data-type="taskList"]) li:last-child,
.ProseMirror ol li:last-child {
  margin-bottom: 0;
}

.blog-content h2,
.ProseMirror h2 {
  display: block;
  font-family: 'Roboto', sans-serif;
  font-size: 32px;
  font-weight: 700;
  line-height: 28px;
  color: #000;
}

.blog-content h3,
.ProseMirror h3 {
  display: block;
  font-family: 'Roboto', sans-serif;
  font-size: 24px;
  font-weight: 700;
  line-height: 26px;
  color: #000;
}

.blog-content h4,
.ProseMirror h4 {
  display: block;
  font-family: 'Roboto', sans-serif;
  font-size: 18px;
  font-weight: 700;
  line-height: 24px;
  color: #000;
}

.blog-content h5,
.ProseMirror h5 {
  display: block;
  font-family: 'Roboto', sans-serif;
  font-size: 16px;
  font-weight: 700;
  line-height: 22px;
  color: #000;
}


.ProseMirror h2::before {
  content: 'H2' !important;
  display: inline-block !important;
  font-size: 12px !important;
  font-weight: 400 !important;
  color: #6b7280 !important;
  background: #f3f4f6 !important;
  padding: 2px 6px !important;
  border-radius: 4px !important;
  margin-right: 8px !important;
  vertical-align: middle !important;
}

.ProseMirror h3::before {
  content: 'H3' !important;
  display: inline-block !important;
  font-size: 12px !important;
  font-weight: 400 !important;
  color: #6b7280 !important;
  background: #f3f4f6 !important;
  padding: 2px 6px !important;
  border-radius: 4px !important;
  margin-right: 8px !important;
  vertical-align: middle !important;
}

.ProseMirror h4::before {
  content: 'H4' !important;
  display: inline-block !important;
  font-size: 12px !important;
  font-weight: 400 !important;
  color: #6b7280 !important;
  background: #f3f4f6 !important;
  padding: 2px 6px !important;
  border-radius: 4px !important;
  margin-right: 8px !important;
  vertical-align: middle !important;
}

.ProseMirror h5::before {
  content: 'H5' !important;
  display: inline-block !important;
  font-size: 12px !important;
  font-weight: 400 !important;
  color: #6b7280 !important;
  background: #f3f4f6 !important;
  padding: 2px 6px !important;
  border-radius: 4px !important;
  margin-right: 8px !important;
  vertical-align: middle !important;
}

.blog-content p,
.ProseMirror p {
  font-family: 'Roboto', sans-serif;
  font-weight: 400;
  font-size: 16px;
  line-height: 1.5;
  color: #000000;
}

.blog-content a {
  color: #0073FF;
  text-decoration: none;
}

.ProseMirror a {
  color: #0073FF;
  text-decoration: none;
  pointer-events: none;
  cursor: text;
}
.blog-content a:hover,
.ProseMirror a:hover {
  text-decoration: underline;
}

.blog-content ul:not([data-type="taskList"]),
.ProseMirror ul:not([data-type="taskList"]) {
  display: block;
  list-style-type: disc;
  padding-left: 1.5rem;
}
.blog-content ul:not([data-type="taskList"]) li,
.ProseMirror ul:not([data-type="taskList"]) li {
  display: list-item;
  list-style-type: disc;
  font-family: 'Roboto', sans-serif;
  font-size: 16px;
  line-height: 1.5;
  color: #000;
}

.blog-content ol,
.ProseMirror ol {
  display: block;
  list-style-type: decimal;
  padding-left: 1.5rem;
}
.blog-content ol li,
.ProseMirror ol li {
  display: list-item;
  list-style-type: decimal;
  font-family: 'Roboto', sans-serif;
  font-size: 16px;
  line-height: 1.5;
  color: #000;
}

.blog-content ul[data-type="taskList"],
.ProseMirror ul[data-type="taskList"] {
  display: block;
  list-style: none;
  padding-left: 0;
  margin-top: 12px;
  margin-bottom: 12px;
}
.blog-content ul[data-type="taskList"] li,
.ProseMirror ul[data-type="taskList"] li {
  list-style: none;
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  font-family: 'Roboto', sans-serif;
  font-size: 16px;
  line-height: 1.5;
  color: #000;
  margin-bottom: 12px;
}
.blog-content ul[data-type="taskList"] li:last-child,
.ProseMirror ul[data-type="taskList"] li:last-child {
  margin-bottom: 0;
}
.ProseMirror ul[data-type="taskList"] li > label {
  flex-shrink: 0;
  margin-top: 0.25rem;
  margin-right: 0;
}
.ProseMirror ul[data-type="taskList"] li > label input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
  margin: 0;
  accent-color: #000;
}
.ProseMirror ul[data-type="taskList"] li > div {
  flex: 1;
}
.ProseMirror ul[data-type="taskList"] li > div > p {
  margin: 0;
}
.ProseMirror ul[data-type="taskList"] li[data-checked="true"] > div > p {
  text-decoration: line-through;
  opacity: 0.6;
}

.blog-content blockquote,
.ProseMirror blockquote {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  padding: 32px;
  gap: 12px;
  max-width: 695px;
  background: #F0F0F0;
  border-radius: 12px;
  box-sizing: border-box;
  border: none;
  quotes: none;
  position: relative;
}

.ProseMirror blockquote::before,
.blog-content blockquote::before {
  content: '';
  position: absolute;
  top: 32px;
  left: 32px;
  width: 23px;
  height: 11px;
  background-image: url("data:image/svg+xml,%3Csvg width='23' height='11' viewBox='0 0 23 11' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M5.54785 0.0380859L2.23438 5.1416L0 5.12891V4.96387L3.74512 0.0380859H5.54785ZM2.23438 5.02734L5.54785 10.1436H3.74512L0 5.20508V5.04004L2.23438 5.02734ZM9.75 0.0380859L6.43652 5.1416L4.20215 5.12891V4.96387L7.94727 0.0380859H9.75ZM6.43652 5.02734L9.75 10.1436H7.94727L4.20215 5.20508V5.04004L6.43652 5.02734ZM12.2383 10.1055L15.5518 5.00195L17.7861 5.01465V5.17969L14.041 10.1055H12.2383ZM12.2383 0H14.041L17.7861 4.93848V5.10352L15.5518 5.11621L12.2383 0ZM16.6689 10.1055L19.9824 5.00195L22.2168 5.01465V5.17969L18.4717 10.1055H16.6689ZM16.6689 0H18.4717L22.2168 4.93848V5.10352L19.9824 5.11621L16.6689 0Z' fill='%230073FF'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-size: contain;
  pointer-events: none;
}

.ProseMirror blockquote p:first-child,
.blog-content blockquote p:first-child {
  max-width: 631px;
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 400;
  font-size: 18px;
  line-height: 21px;
  color: #000000;
  margin: 20px 0 0 0;
}

.ProseMirror blockquote p:last-child:not(:first-child),
.blog-content blockquote p:last-child:not(:first-child) {
  font-family: 'Roboto', sans-serif;
  font-weight: 400;
  font-size: 18px;
  line-height: 21px;
  color: #000000;
  opacity: 0.3;
  text-align: left;
  width: 100%;
  max-width: 631px;
  margin: 0;
}
.ProseMirror blockquote p:last-child:not(:first-child)::before,
.blog-content blockquote p:last-child:not(:first-child)::before {
  content: '— ';
}

.blog-image,
.ProseMirror .blog-image {
  display: block;
  max-width: 100%;
  height: 572px;
  object-fit: contain;
  width: 100%;
  background-color: #B3C3DE4D;
}

.blog-content .blog-image-caption,
.ProseMirror .blog-image-caption {
  margin-top: 0 !important;
  margin-bottom: 48px;
  font-family: 'Roboto', sans-serif;
  font-size: 16px;
  line-height: 19px;
  color: #000000;
  opacity: 0.47;
  display: block;
  text-align: left;
  max-width: 100%;
}

.ProseMirror table,
.blog-content table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 32px;
  margin-bottom: 48px;
}
.ProseMirror .tableWrapper:has(+ p.blog-image-caption) {
margin-bottom: 12px !important;
}

/* Убираем дефолтный margin у tableWrapper, если после него подпись */
.ProseMirror .tableWrapper {
  margin-bottom: 0;
}

/* Таблица внутри tableWrapper — margin-bottom 48px по умолчанию */
.ProseMirror .tableWrapper table {
  margin-bottom: 48px;
}

/* Если после tableWrapper идёт подпись — убираем margin у таблицы */
.ProseMirror .tableWrapper:has(+ p.blog-image-caption) table {
  margin-bottom: 12px !important;
}
.ProseMirror table th,
.ProseMirror table td,
.blog-content table th,
.blog-content table td {
  border: 1px solid #d1d5db;
  padding: 8px 12px;
  text-align: left;
  vertical-align: top;
}
.ProseMirror table td,
.ProseMirror table th {
  /* Убедимся, что позиция relative для абсолютного позиционирования ручки */
  position: relative;
}
.ProseMirror table th,
.blog-content table th {
  background-color: #f9fafb;
  font-weight: 600;
}

.ProseMirror table p,
.blog-content table p {
  margin-top: 0;
  margin-bottom: 0;
}

.ProseMirror .selectedCell {
  background-color: #dbeafe;
  border-color: #3b82f6;
}
.ProseMirror .column-resize-handle {
  position: absolute;
  right: -2px;
  top: 0;
  bottom: -2px;
  width: 5px;
  background-color: transparent;
  cursor: col-resize; /* Курсор при наведении на саму ручку */
  z-index: 20;
  pointer-events: auto;
}
.ProseMirror .column-resize-handle:hover {
  background-color: rgba(59, 130, 246, 0.5);
}

.ProseMirror .cta-block-editor {
  margin-top: 32px;
  margin-bottom: 48px;
}

.ProseMirror.resize-cursor,
.ProseMirror.resize-cursor * {
  cursor: col-resize !important;
}
.blog-content .cta-block {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  padding: 32px;
  width: 695px;
  max-width: 100%;
  background: #FFFFFF;
  border: 1px solid #DFDFDF;
  backdrop-filter: blur(25px);
  border-radius: 12px;
  margin-top: 32px;
  margin-bottom: 48px;
}
.blog-content .cta-block .cta-icon {
  margin-bottom: 12px;
}
.blog-content .cta-block .cta-title {
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 400;
  font-size: 24px;
  line-height: 28px;
  color: #000000;
  margin: 0 0 8px 0;
}
.blog-content .cta-block .cta-description {
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 400;
  font-size: 18px;
  line-height: 21px;
  color: #000000;
  opacity: 0.5;
  margin: 0 0 16px 0;
}
.blog-content .cta-block .cta-button {
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  align-items: center;
  padding: 24px 32px;
  gap: 24px;
  width: 631px;
  max-width: 100%;
  height: 72px;
  background: #000000;
  backdrop-filter: blur(2px);
  color: #fff;
  text-decoration: none;
  font-family: 'Roboto', sans-serif;
  font-size: 18px;
  font-weight: 400;
  border-radius: 0;
  box-sizing: border-box;
}
.blog-content .cta-block .cta-button:hover {
  background: #222;
}


.ProseMirror .internal-link-block-editor {
  margin-top: 32px;
  margin-bottom: 48px;
}
.blog-content .internal-link-block {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  padding: 24px;
  width: 695px;
  max-width: 100%;
  background: #000000;
  backdrop-filter: blur(2px);
  border-radius: 12px;
  margin-top: 32px;
  margin-bottom: 48px;
}
.blog-content .internal-link-block .internal-link-text {
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 400;
  font-size: 18px;
  line-height: 21px;
  color: #ffffff;
  margin: 0 0 16px 0;
}
.blog-content .internal-link-block .internal-link-button {
  display: inline-flex;
  align-items: center;
  padding: 12px 24px;
  background: #fff;
  color: #000;
  text-decoration: none;
  font-family: 'Roboto', sans-serif;
  font-size: 16px;
  font-weight: 400;
  border-radius: 8px;
}
.blog-content .internal-link-block .internal-link-button:hover {
  background: #f0f0f0;
}


.ProseMirror hr,
.blog-content hr {
  border: none;
  border-top: 2px solid #e5e7eb;
  margin-top: 32px;
  margin-bottom: 32px;
}


.ProseMirror sub,
.blog-content sub {
  font-size: 0.75em;
  vertical-align: sub;
}
.ProseMirror sup,
.blog-content sup {
  font-size: 0.75em;
  vertical-align: super;
}


@media (max-width: 1024px) {
  .blog-content table {
    display: block;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  .blog-content table th,
  .blog-content table td {
    min-width: 150px;
  }
  .blog-content .cta-block {
    width: 100%;
  }
  .blog-content .cta-block .cta-button {
    width: 100%;
    padding: 16px 20px;
    height: auto;
  }
  .blog-content .internal-link-block {
    width: 100%;
    padding: 16px;
  }
}

@media (max-width: 640px) {
  .blog-content table th,
  .blog-content table td {
    min-width: 120px;
    padding: 10px 8px;
    font-size: 13px;
  }
}


.blog-content h2,
.blog-content h3,
.blog-content h4,
.blog-content h5 {
  scroll-margin-top: 100px;
  scroll-snap-margin-top: 100px;
}
.blog-content h2:target,
.blog-content h3:target,
.blog-content h4:target,
.blog-content h5:target {
  animation: highlight 2s ease-out;
}
@keyframes highlight {
  0% { background-color: rgba(16, 185, 129, 0.2); }
  100% { background-color: transparent; }
}

.ProseMirror .key-insight {
  display: flex;
  width: 656px;
  max-width: 100%;
  padding: 32px;
  flex-direction: column;
  align-items: flex-start;
  gap: 21px;
  border-radius: 12px;
  background: #6E826D;
  box-sizing: border-box;
  margin-top: 32px;
  margin-bottom: 32px;
}


.ProseMirror .key-insight h4 {
  align-self: stretch;
  color: #F5F5F5;
  font-family: 'Roboto', sans-serif;
  font-size: 21px;
  font-style: normal;
  font-weight: 400;
  line-height: normal;
  margin: 0;
  width: 100%;
}

.ProseMirror .key-insight h4::before {
  content: '';
  display: inline-block !important;
  font-size: 16px !important;
  font-weight: 400 !important;
  margin-right: 8px !important;
  vertical-align: middle !important;
  background: none !important;
  padding: 0 !important;
  border-radius: 0 !important;
}
.ProseMirror > p::before {
  content: 'Text' !important;
  display: inline-block !important;
  font-size: 12px !important;
  font-weight: 400 !important;
  color: #6b7280 !important;
  background: #f3f4f6 !important;
  padding: 2px 6px !important;
  border-radius: 4px !important;
  margin-right: 8px !important;
  vertical-align: middle !important;
}

/* Исключаем параграфы внутри таблиц, цитат и подписей к картинкам, чтобы метка не мешала */
.ProseMirror table p::before,
.ProseMirror blockquote p::before,
.ProseMirror .blog-image-caption::before {
  content: none !important;
  display: none !important;
}
/* Отступ в админке — с меткой слева */
.ProseMirror p.blog-indent {
  min-height: 24px;
  margin-top: 12px;
  margin-bottom: 12px;
}

.ProseMirror p.blog-indent::before {
  content: 'Отступ' !important;
  display: inline-block !important;
  font-size: 12px !important;
  font-weight: 400 !important;
  color: #6b7280 !important;
  background: #f3f4f6 !important;
  padding: 2px 6px !important;
  border-radius: 4px !important;
  margin-right: 8px !important;
  vertical-align: middle !important;
}
body.row-resizing {
  cursor: row-resize !important;
  user-select: none;
}
.ProseMirror table tr {
  position: relative;
}

/* Визуальный индикатор при наведении на границу строки */
.ProseMirror table tr.row-hover-resize {
  cursor: row-resize !important;
}


/* Подсветка строки при drag */
/* Подсветка строки при drag */
.ProseMirror table tr.row-dragging {
  background-color: rgba(59, 130, 246, 0.1) !important;
}


/* Глобальный курсор при drag */
body.row-resizing {
  cursor: row-resize !important;
  user-select: none;
}

body.row-resizing * {
  cursor: row-resize !important;
}
</style>