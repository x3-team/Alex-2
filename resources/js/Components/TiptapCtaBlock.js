import { Node, mergeAttributes } from '@tiptap/core'

export const CtaBlock = Node.create({
    name: 'ctaBlock',
    group: 'block',
    atom: true, // 🔹 Атомарный блок — нельзя редактировать внутри

    addAttributes() {
        return {
            title: { default: '' },
            description: { default: '' },
            buttonText: { default: '' },
            buttonUrl: { default: '' },
        }
    },

    parseHTML() {
        return [{ tag: 'div.cta-block' }]
    },

    renderHTML({ node, HTMLAttributes }) {
        return ['div', mergeAttributes(HTMLAttributes, { class: 'cta-block' }),
            // 🔹 SVG иконка — ПОЛНЫЙ path
            ['div', { class: 'cta-icon' },
                ['svg', { width: '42', height: '42', viewBox: '0 0 42 42', fill: 'none', xmlns: 'http://www.w3.org/2000/svg' },
                    ['path', {
                        d: 'M22.1181 38.1005C22.4323 37.8396 22.6453 37.477 22.7203 37.0755C23.3238 33.4873 25.0307 30.1764 27.6036 27.6036C30.1764 25.0307 33.4873 23.3238 37.0755 22.7203C37.477 22.6453 37.8396 22.4323 38.1005 22.1181C38.3614 21.804 38.5042 21.4084 38.5042 21C38.5042 20.5917 38.3614 20.1961 38.1005 19.882C37.8396 19.5678 37.477 19.3548 37.0755 19.2798C33.4873 18.6763 30.1764 16.9694 27.6036 14.3965C25.0307 11.8236 23.3238 8.51275 22.7203 4.92454C22.6453 4.5231 22.4323 4.16052 22.1181 3.8996C21.804 3.63868 21.4084 3.49585 21 3.49585C20.5917 3.49585 20.1961 3.63868 19.882 3.8996C19.5678 4.16052 19.3548 4.5231 19.2798 4.92454C18.6763 8.51275 16.9694 11.8236 14.3965 14.3965C11.8236 16.9694 8.51275 18.6763 4.92454 19.2798C4.5231 19.3548 4.16052 19.5678 3.8996 19.882C3.63868 20.1961 3.49585 20.5917 3.49585 21C3.49585 21.4084 3.63868 21.804 3.8996 22.1181C4.16052 22.4323 4.5231 22.6453 4.92454 22.7203C8.51275 23.3238 11.8236 25.0307 14.3965 27.6036C16.9694 30.1764 18.6763 33.4873 19.2798 37.0755C19.3548 37.477 19.5678 37.8396 19.882 38.1005C20.1961 38.3614 20.5917 38.5042 21 38.5042C21.4084 38.5042 21.804 38.3614 22.1181 38.1005Z',
                        stroke: 'black',
                        'stroke-width': '2',
                        'stroke-linecap': 'round',
                        'stroke-linejoin': 'round'
                    }]
                ]
            ],
            ['div', { class: 'cta-wrap' },
                // 🔹 Заголовок
                ['h3', { class: 'cta-title' }, node.attrs.title || 'Заголовок CTA'],
                // 🔹 Описание
                ['p', { class: 'cta-description' }, node.attrs.description || 'Описание CTA'],
            ],
            // 🔹 Кнопка
            ['a', {
                class: 'cta-button',
                href: node.attrs.buttonUrl || '#'
            }, node.attrs.buttonText || 'Перейти']
        ]
    },

    addNodeView() {
        return ({ node }) => {
            const dom = document.createElement('div')
            dom.className = 'cta-block-editor'
            dom.contentEditable = false

            dom.innerHTML = `
        <div style="border: 2px dashed #3b82f6; border-radius: 12px; padding: 24px; background: #eff6ff; margin: 1rem 0;">
          <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2">
              <path d="M12 2L15 8L22 9L17 14L18 21L12 18L6 21L7 14L2 9L9 8L12 2Z" fill="none" stroke="#3b82f6"/>
            </svg>
            <span style="color: #3b82f6; font-weight: 600; font-size: 14px;">CTA-блок</span>
          </div>
          <div style="font-size: 18px; font-weight: 600; color: #111; margin-bottom: 8px;">${node.attrs.title || 'Заголовок CTA'}</div>
          <div style="font-size: 14px; color: #666; margin-bottom: 12px; opacity: 0.7;">${node.attrs.description || 'Описание CTA'}</div>
          <a href="${node.attrs.buttonUrl || '#'}" style="display: inline-block; background: #000; color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-size: 14px;">
            ${node.attrs.buttonText || 'Перейти'}
          </a>
        </div>
      `
            return { dom }
        }
    }
})

export const InternalLinkBlock = Node.create({
    name: 'internalLinkBlock',
    group: 'block',
    atom: true,

    addAttributes() {
        return {
            text: { default: '' },
            url: { default: '' },
        }
    },

    parseHTML() {
        return [{ tag: 'div.internal-link-block' }]
    },

    renderHTML({ node, HTMLAttributes }) {
        return ['div', mergeAttributes(HTMLAttributes, { class: 'internal-link-block' }),
            ['p', { class: 'internal-link-text' }, node.attrs.text || ''],
            ['a', {
                class: 'internal-link-button',
                href: node.attrs.url || '#'
            }, 'Читать →']
        ]
    },

    addNodeView() {
        return ({ node }) => {
            const dom = document.createElement('div')
            dom.className = 'internal-link-block-editor'
            dom.contentEditable = false

            dom.innerHTML = `
        <div style="border: 2px dashed #10b981; border-radius: 12px; padding: 24px; background: #ecfdf5; margin: 1rem 0;">
          <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2">
              <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" stroke="#10b981"/>
              <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" stroke="#10b981"/>
            </svg>
            <span style="color: #10b981; font-weight: 600; font-size: 14px;">Перелинковка</span>
          </div>
          <div style="font-size: 16px; color: #111; margin-bottom: 12px;">${node.attrs.text || 'Текст ссылки'}</div>
          <a href="${node.attrs.url || '#'}" style="display: inline-block; background: #000; color: #fff; padding: 12px 32px; border-radius: 8px; text-decoration: none; font-size: 14px;">
            Читать →
          </a>
        </div>
      `
            return { dom }
        }
    }
})