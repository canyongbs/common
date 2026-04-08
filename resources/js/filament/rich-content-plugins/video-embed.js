import { Node, mergeAttributes } from '@tiptap/core'

export default Node.create({
    name: 'videoEmbed',

    group: 'block',

    atom: true,

    addAttributes() {
        return {
            src: {
                default: null,
                parseHTML: (element) =>
                    element.getAttribute('data-video-src') ||
                    element.querySelector('iframe')?.getAttribute('src') ||
                    element.querySelector('video')?.getAttribute('src'),
                renderHTML: () => ({}),
            },
            type: {
                default: 'video',
                parseHTML: (element) =>
                    element.getAttribute('data-video-type') || 'video',
                renderHTML: () => ({}),
            },
            width: {
                default: '100%',
                parseHTML: (element) =>
                    element.getAttribute('data-video-width') || '100%',
                renderHTML: () => ({}),
            },
            height: {
                default: '315',
                parseHTML: (element) =>
                    element.getAttribute('data-video-height') || '315',
                renderHTML: () => ({}),
            },
        }
    },

    parseHTML() {
        return [
            {
                tag: 'div[data-video-embed]',
            },
        ]
    },

    renderHTML({ node }) {
        const { src, type, width, height } = node.attrs

        const wrapperAttrs = {
            'data-video-embed': '',
            'data-video-type': type,
            'data-video-src': src,
            'data-video-width': width,
            'data-video-height': height,
        }

        if (type === 'youtube' || type === 'vimeo') {
            return [
                'div',
                {
                    ...wrapperAttrs,
                    style: 'position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%;',
                },
                [
                    'iframe',
                    {
                        src,
                        width: '100%',
                        height: height || '315',
                        frameborder: '0',
                        allowfullscreen: 'true',
                        allow: 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture',
                        style: 'position: absolute; top: 0; left: 0; width: 100%; height: 100%;',
                    },
                ],
            ]
        }

        return [
            'div',
            {
                ...wrapperAttrs,
                style: 'max-width: 100%;',
            },
            [
                'video',
                {
                    src,
                    controls: 'true',
                    width: '100%',
                },
            ],
        ]
    },
})
