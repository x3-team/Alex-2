import { Node, mergeAttributes } from '@tiptap/core'

export const CustomBlockquote = Node.create({
    name: 'blockquote',
    group: 'block',
    content: 'block+',

    parseHTML() {
        return [{ tag: 'blockquote' }]
    },

    renderHTML({ HTMLAttributes }) {
        return ['blockquote', mergeAttributes(HTMLAttributes),
            // 🔹 SVG-иконка перед текстом
            ['div', { class: 'blockquote-icon' },
                ['svg', {
                    width: '23',
                    height: '11',
                    viewBox: '0 0 23 11',
                    fill: 'none',
                    xmlns: 'http://www.w3.org/2000/svg'
                },
                    ['path', {
                        d: 'M5.54785 0.0380859L2.23438 5.1416L0 5.12891V4.96387L3.74512 0.0380859H5.54785ZM2.23438 5.02734L5.54785 5.02734L5.54785 10.1436H3.74512L0 5.20508V5.04004L2.23438 5.02734ZM9.75 0.0380859L6.43652 5.1416L4.20215 5.12891V4.96387L7.94727 0.0380859H9.75ZM6.43652 5.02734L9.75 5.02734L9.75 10.1436H7.94727L4.20215 5.20508V5.04004L6.43652 5.02734ZM12.2383 10.1055L15.5518 5.00195L17.7861 5.01465V5.17969L14.041 10.1055H12.2383ZM12.2383 0H14.041L17.7861 4.93848V5.10352L15.5518 5.11621L12.2383 0ZM16.6689 10.1055L19.9824 5.00195L22.2168 5.01465V5.17969L18.4717 10.1055H16.6689ZM16.6689 0H18.4717L22.2168 4.93848V5.10352L19.9824 5.11621L16.6689 0Z',
                        fill: '#0073FF'
                    }]
                ]
            ],
            0
        ]
    }
})