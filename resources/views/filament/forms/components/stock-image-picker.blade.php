{{--
<COPYRIGHT>

    Copyright © 2016-2025, Canyon GBS LLC. All rights reserved.

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
--}}
<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        class="space-y-4"
        x-data="{
            state: $wire.$entangle('{{ $getStatePath() }}'),
            query: '',
            images: [],
            currentPage: 1,
            lastPage: 1,
            total: 0,
            loading: false,
            error: null,
            selectedImage: null,
            searchTimeout: null,
        
            init() {
                this.$watch('query', () => {
                    clearTimeout(this.searchTimeout);
        
                    this.searchTimeout = setTimeout(() => {
                        this.currentPage = 1;
                        this.loadImages();
                    }, 500);
                });
            },
        
            async loadImages() {
                if (!this.query.trim()) {
                    this.images = [];
                    this.currentPage = 1;
                    this.lastPage = 1;
                    this.total = 0;
        
                    return;
                }
        
                this.loading = true;
                this.error = null;
        
                try {
                    const response = await fetch(@js($getUrl()), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            search: this.query,
                            page: this.currentPage
                        })
                    });
        
                    if (!response.ok) {
                        throw new Error('Failed to fetch images');
                    }
        
                    const data = await response.json();
                    this.images = data.data || [];
                    this.currentPage = data.current_page || 1;
                    this.lastPage = data.last_page || 1;
                    this.total = data.total || 0;
                } catch (error) {
                    this.error = error.message;
                } finally {
                    this.loading = false;
                }
            },
        
            selectImage(image) {
                this.selectedImage = image;
                this.state = {
                    src: image.url,
                    alt: image.title
                };
            },
        
            goToPage(page) {
                if (page >= 1 && page <= this.lastPage && page !== this.currentPage) {
                    this.currentPage = page;
                    this.loadImages();
                }
            },
        
            isSelected(image) {
                return this.selectedImage && this.selectedImage.url === image.url;
            }
        }"
    >
        <x-filament::input.wrapper
            inline-prefix
            prefix-icon="heroicon-m-magnifying-glass"
        >
            <x-filament::input
                type="search"
                x-model="query"
                placeholder="Search stock images..."
            />
        </x-filament::input.wrapper>

        <div
            class="flex justify-center py-8"
            x-show="loading"
        >
            <x-filament::loading-indicator class="h-8 w-8 text-gray-500 dark:text-gray-400" />
        </div>

        <div
            class="bg-danger-50 dark:bg-danger-900/20 border-danger-200 dark:border-danger-800 rounded-md border p-4"
            x-show="error"
        >
            <div class="flex">
                <div class="flex-shrink-0">
                    @svg('heroicon-m-exclamation-circle', 'h-5 w-5 text-danger-400 dark:text-danger-300')
                </div>

                <div class="ml-3">
                    <p
                        class="text-danger-700 dark:text-danger-200 text-sm"
                        x-text="error"
                    ></p>
                </div>
            </div>
        </div>

        <div
            class="text-sm text-gray-600 dark:text-gray-400"
            x-show="!loading && !error && total > 0"
        >
            Showing <span x-text="images.length"></span> of <span x-text="total"></span> images
        </div>

        <div
            class="py-12 text-center"
            x-show="!loading && !error && images.length === 0 && !query.trim()"
        >
            @svg('heroicon-o-photo', 'mx-auto h-12 w-12 text-gray-400 dark:text-gray-500')

            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Search for stock images</h3>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Enter a search term above to find images.</p>
        </div>

        <div
            class="py-12 text-center"
            x-show="!loading && !error && images.length === 0 && query.trim()"
        >
            @svg('heroicon-o-photo', 'mx-auto h-12 w-12 text-gray-400 dark:text-gray-500')

            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No images found</h3>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try adjusting your search terms.</p>
        </div>

        <div
            class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5"
            x-show="!loading && !error && images.length > 0"
        >
            <template
                x-for="image in images"
                x-bind:key="image.url"
            >
                <div
                    class="group relative aspect-square cursor-pointer overflow-hidden rounded-lg bg-gray-100 transition-all duration-200 dark:bg-gray-800"
                    x-on:click="selectImage(image)"
                    x-bind:class="{
                        'ring-2 ring-primary-500 ring-offset-2 dark:ring-offset-gray-800': isSelected(image),
                        'hover:ring-2 hover:ring-gray-300 dark:hover:ring-gray-600 hover:ring-offset-2 dark:hover:ring-offset-gray-800':
                            !isSelected(image)
                    }"
                >
                    <img
                        class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                        x-bind:src="image.preview_url"
                        x-bind:alt="image.title"
                        loading="lazy"
                    />

                    <div class="absolute inset-0 bg-black/0 transition-all duration-200 group-hover:bg-black/20"></div>

                    <div
                        class="bg-primary-500 absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full"
                        x-show="isSelected(image)"
                    >
                        @svg('heroicon-m-check', 'w-4 h-4 text-white')
                    </div>

                    <div
                        class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent p-2 opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                        <p
                            class="truncate text-xs font-medium text-white"
                            x-text="image.title"
                        ></p>
                    </div>
                </div>
            </template>
        </div>

        <div
            class="flex items-center justify-between"
            x-show="!loading && !error && lastPage > 1"
        >
            <div class="flex items-center space-x-4">
                <button
                    class="flex items-center text-sm text-gray-500 transition-colors duration-200 dark:text-gray-400"
                    type="button"
                    x-on:click="goToPage(currentPage - 1)"
                    x-bind:disabled="currentPage === 1"
                    x-bind:class="{
                        'opacity-50 cursor-not-allowed': currentPage === 1,
                        'hover:text-primary-600 dark:hover:text-primary-400': currentPage !== 1
                    }"
                >
                    @svg('heroicon-c-chevron-left', 'w-3 h-3 mr-1')

                    Previous
                </button>

                <div class="flex items-center space-x-2">
                    <template
                        x-for="page in Array.from({ length: Math.min(5, lastPage) }, (_, i) => {
                        const start = Math.max(1, Math.min(currentPage - 2, lastPage - 4));
                        return start + i;
                    }).filter(p => p <= lastPage)"
                        x-bind:key="page"
                    >
                        <button
                            class="rounded px-2 py-1 text-sm transition-colors duration-200"
                            type="button"
                            x-on:click="goToPage(page)"
                            x-bind:class="{
                                'text-primary-600 dark:text-primary-400 font-medium': page === currentPage,
                                'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300': page !==
                                    currentPage
                            }"
                            x-text="page"
                        ></button>
                    </template>
                </div>

                <button
                    class="flex items-center text-sm text-gray-500 transition-colors duration-200 dark:text-gray-400"
                    type="button"
                    x-on:click="goToPage(currentPage + 1)"
                    x-bind:disabled="currentPage === lastPage"
                    x-bind:class="{
                        'opacity-50 cursor-not-allowed': currentPage === lastPage,
                        'hover:text-primary-600 dark:hover:text-primary-400': currentPage !== lastPage
                    }"
                >
                    Next

                    @svg('heroicon-c-chevron-right', 'w-3 h-3 mr-1')
                </button>
            </div>

            <span class="text-sm text-gray-500 dark:text-gray-400">
                Page <span x-text="currentPage"></span> of <span x-text="lastPage"></span>
            </span>
        </div>

        <div
            class="mt-4 rounded-lg bg-gray-50 p-4 dark:bg-gray-800"
            x-show="selectedImage"
        >
            <h4 class="mb-2 text-sm font-medium text-gray-900 dark:text-gray-100">Selected Image:</h4>

            <div class="flex items-center space-x-3">
                <img
                    class="h-16 w-16 rounded-lg object-cover"
                    x-bind:src="selectedImage?.preview_url"
                    x-bind:alt="selectedImage?.title"
                >

                <div class="min-w-0 flex-1">
                    <p
                        class="truncate text-sm font-medium text-gray-900 dark:text-gray-100"
                        x-text="selectedImage?.title"
                    ></p>
                    <p
                        class="truncate text-sm text-gray-500 dark:text-gray-400"
                        x-text="selectedImage?.url"
                    ></p>
                </div>

                <button
                    class="text-gray-400 transition-colors duration-200 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                    type="button"
                    x-on:click="selectedImage = null; state = null"
                >
                    @svg('heroicon-m-x-mark', 'w-5 h-5')
                </button>
            </div>
        </div>
    </div>
</x-dynamic-component>
