/*
<COPYRIGHT>

    Copyright © 2016-2026, Canyon GBS LLC. All rights reserved.

    Canyon GBS Common is licensed under the Elastic License 2.0. For more details,
    see https://github.com/canyongbs/common/blob/main/LICENSE.

    Notice:

    - You may not provide the software to third parties as a hosted or managed
      service, where the service provides users with access to any substantial set of
      the features or functionality of the software.
    - You may not move, change, disable, or circumvent the license key functionality
      in the software, and you may not remove or obscure any functionality in the
      software that is protected by the license key.
    - You may not alter, remove, or obscure any licensing, copyright, or other notices
      of the licensor in the software. Any use of the licensor’s trademarks is subject
      to applicable law.
    - Canyon GBS LLC respects the intellectual property rights of others and expects the
      same in return. Canyon GBS™ and Canyon GBS Common are registered trademarks of
      Canyon GBS LLC, and we are committed to enforcing and protecting our trademarks
      vigorously.
    - The software solution, including services, infrastructure, and code, is offered as a
      Software as a Service (SaaS) by Canyon GBS LLC.
    - Use of this software implies agreement to the license terms and conditions as stated
      in the Elastic License 2.0.

    For more information or inquiries please visit our website at
    https://www.canyongbs.com or contact us via email at legal@canyongbs.com.

</COPYRIGHT>
*/
import { Node, ResizableNodeView } from '@tiptap/core';

export default Node.create({
    name: 'videoEmbed',

    group: 'block',

    atom: true,

    addOptions() {
        return {
            resize: {
                enabled: true,
                directions: ['bottom-left', 'bottom-right'],
            },
        };
    },

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
                parseHTML: (element) => element.getAttribute('data-video-type') || 'video',
                renderHTML: () => ({}),
            },
            width: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-video-width') || null,
                renderHTML: () => ({}),
            },
            height: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-video-height') || null,
                renderHTML: () => ({}),
            },
        };
    },

    parseHTML() {
        return [
            {
                tag: 'div[data-video-embed]',
            },
        ];
    },

    renderHTML({ node }) {
        const { src, type, width, height } = node.attrs;

        const wrapperAttrs = {
            'data-video-embed': '',
            'data-video-type': type,
            'data-video-src': src,
            'data-video-width': width,
            'data-video-height': height,
        };

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
            ];
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
        ];
    },

    addNodeView() {
        const { resize } = this.options;

        if (!resize?.enabled || typeof document === 'undefined') {
            return null;
        }

        return ({ node, getPos, editor }) => {
            const { src, type, width, height } = node.attrs;

            let mediaElement;

            if (type === 'youtube' || type === 'vimeo') {
                mediaElement = document.createElement('iframe');
                mediaElement.setAttribute('src', src);
                mediaElement.setAttribute('frameborder', '0');
                mediaElement.setAttribute('allowfullscreen', 'true');
                mediaElement.setAttribute(
                    'allow',
                    'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture',
                );
                mediaElement.style.width = '100%';
                mediaElement.style.height = '100%';
                mediaElement.style.display = 'block';
                mediaElement.style.pointerEvents = 'none';
            } else {
                mediaElement = document.createElement('video');
                mediaElement.setAttribute('src', src);
                mediaElement.setAttribute('controls', 'true');
                mediaElement.style.width = '100%';
                mediaElement.style.display = 'block';
                mediaElement.style.pointerEvents = 'none';
            }

            const container = document.createElement('div');
            container.style.aspectRatio = '16 / 9';
            container.appendChild(mediaElement);

            if (width) {
                container.style.width = /^\d+$/.test(String(width)) ? `${width}px` : width;
            }

            if (height) {
                container.style.height = /^\d+$/.test(String(height)) ? `${height}px` : height;
                container.style.aspectRatio = '';
            }

            const nodeView = new ResizableNodeView({
                element: container,
                editor,
                node,
                getPos,
                onResize: (w, h) => {
                    container.style.width = `${w}px`;
                    container.style.height = `${h}px`;
                    container.style.aspectRatio = '';
                },
                onCommit: (w, h) => {
                    const pos = getPos();

                    if (pos !== undefined) {
                        this.editor
                            .chain()
                            .setNodeSelection(pos)
                            .updateAttributes(this.name, {
                                width: w,
                                height: h,
                            })
                            .run();
                    }
                },
                onUpdate: (updatedNode) => updatedNode.type === node.type,
                options: {
                    directions: resize.directions,
                    preserveAspectRatio: true,
                },
            });

            return nodeView;
        };
    },
});
