class ImageCropper {
    constructor(options = {}) {
        this.container = null;
        this.canvas = null;
        this.ctx = null;
        this.image = null;
        this.cropBox = {
            x: 0,
            y: 0,
            width: 100,
            height: 100
        };
        this.aspectRatio = options.aspectRatio || 16 / 9;
        this.minCropSize = options.minCropSize || 50;
        this.maxZoom = options.maxZoom || 3;
        this.minZoom = options.minZoom || 0.1;
        this.zoom = 1;
        this.imagePosition = { x: 0, y: 0 };
        this.isDragging = false;
        this.dragStart = { x: 0, y: 0 };
        this.isResizing = false;
        this.resizeHandle = null;
        this.onCropChange = options.onCropChange || null;
        
        this.setupElements();
        this.attachEvents();
    }
    
    setupElements() {
        const containerHtml = `
            <div class="image-cropper-wrapper w-full max-w-3xl mx-auto">
                <!-- Preview Container -->
                <div class="image-cropper-container relative w-full bg-black min-h-96 overflow-hidden rounded-xl shadow-lg border-2 border-gray-200">
                    <canvas class="image-cropper-canvas block w-full h-auto cursor-move"></canvas>
                    <div class="crop-overlay absolute inset-0 pointer-events-none">
                        <div class="crop-box absolute pointer-events-auto cursor-move" style="border: 2px solid #3b82f6; box-shadow: 0 0 0 9999px rgba(0,0,0,0.6);">
                            <!-- Resize Handles -->
                            <div class="crop-handle nw absolute top-0 left-0 -translate-x-1/2 -translate-y-1/2 w-5 h-5 bg-blue-500 border-2 border-white rounded-full shadow-lg cursor-nw-resize pointer-events-auto hover:scale-110 transition-transform"></div>
                            <div class="crop-handle ne absolute top-0 right-0 translate-x-1/2 -translate-y-1/2 w-5 h-5 bg-blue-500 border-2 border-white rounded-full shadow-lg cursor-ne-resize pointer-events-auto hover:scale-110 transition-transform"></div>
                            <div class="crop-handle sw absolute bottom-0 left-0 -translate-x-1/2 translate-y-1/2 w-5 h-5 bg-blue-500 border-2 border-white rounded-full shadow-lg cursor-sw-resize pointer-events-auto hover:scale-110 transition-transform"></div>
                            <div class="crop-handle se absolute bottom-0 right-0 translate-x-1/2 translate-y-1/2 w-5 h-5 bg-blue-500 border-2 border-white rounded-full shadow-lg cursor-se-resize pointer-events-auto hover:scale-110 transition-transform"></div>
                        </div>
                    </div>
                </div>

                <!-- Info Section -->
                <div class="image-cropper-info mt-4 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-100 rounded-lg">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
                        <div>
                            <p class="text-xs text-gray-600 font-medium mb-0.5">Crop Area</p>
                            <p class="text-lg font-bold text-blue-600"><span class="crop-dimensions">0 × 0 px</span></p>
                        </div>
                        <div class="h-12 border-r border-blue-200 hidden sm:block"></div>
                        <div>
                            <p class="text-xs text-gray-600 font-medium mb-0.5">File Limits</p>
                            <p class="text-sm text-gray-700 font-medium">Max 5MB (JPG, PNG, GIF, WebP)</p>
                        </div>
                    </div>
                </div>

                <!-- Controls Section -->
                <div class="image-cropper-controls mt-6 space-y-5">
                    <!-- Zoom Control -->
                    <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                        <div class="flex items-center justify-between mb-3">
                            <label class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M5 9a4 4 0 118 0 4 4 0 01-8 0z"/><path d="M9 16c.946 0 1.845-.123 2.707-.357l3.086 3.086a1.5 1.5 0 102.122-2.121l-3.086-3.086A7 7 0 119 2a1 1 0 000 2 5 5 0 100 10z"/>
                                </svg>
                                Zoom Level
                            </label>
                            <span class="text-sm font-bold text-blue-600 bg-blue-50 px-3 py-1 rounded zoom-value">100%</span>
                        </div>
                        <input type="range" class="zoom-slider w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-blue-500" min="0.1" max="3" step="0.1" value="1">
                        <div class="flex justify-between text-xs text-gray-500 mt-2">
                            <span>10%</span>
                            <span>300%</span>
                        </div>
                    </div>

                    <!-- Aspect Ratio & Reset -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                            <label class="text-sm font-semibold text-gray-900 flex items-center gap-2 mb-3">
                                <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"/>
                                </svg>
                                Aspect Ratio
                            </label>
                            <select class="aspect-ratio-select w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-900 bg-white hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                <option value="1.7778">16:9 (Landscape)</option>
                                <option value="1.3333">4:3 (Standard)</option>
                                <option value="1">1:1 (Square)</option>
                                <option value="0">Free</option>
                            </select>
                        </div>

                        <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex items-end">
                            <button class="reset-btn w-full px-4 py-2 bg-gradient-to-r from-gray-600 to-gray-700 hover:from-gray-700 hover:to-gray-800 text-white font-semibold rounded-lg transition-all shadow-md hover:shadow-lg active:scale-95 flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 1119.778 5.873 1 1 0 11-1.497-.874A5.002 5.002 0 1010.5 8a.999.999 0 00-1 1V5a1 1 0 11-2 0V3a1 1 0 011-1z" clip-rule="evenodd"/>
                                </svg>
                                Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        const wrapper = document.createElement('div');
        wrapper.innerHTML = containerHtml;
        this.container = wrapper.firstElementChild;
        
        this.canvas = this.container.querySelector('.image-cropper-canvas');
        this.ctx = this.canvas.getContext('2d');
        this.cropBoxEl = this.container.querySelector('.crop-box');
        this.zoomSlider = this.container.querySelector('.zoom-slider');
        this.aspectRatioSelect = this.container.querySelector('.aspect-ratio-select');
        this.resetBtn = this.container.querySelector('.reset-btn');
        this.cropDimensionsEl = this.container.querySelector('.crop-dimensions');
    }
    
    attachEvents() {
        this.canvas.addEventListener('mousedown', this.handleCanvasMouseDown.bind(this));
        this.canvas.addEventListener('mousemove', this.handleCanvasMouseMove.bind(this));
        this.canvas.addEventListener('mouseup', this.handleCanvasMouseUp.bind(this));
        this.canvas.addEventListener('mouseleave', this.handleCanvasMouseUp.bind(this));
        this.canvas.addEventListener('wheel', this.handleWheel.bind(this), { passive: false });
        
        this.canvas.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: false });
        this.canvas.addEventListener('touchmove', this.handleTouchMove.bind(this), { passive: false });
        this.canvas.addEventListener('touchend', this.handleTouchEnd.bind(this));
        
        const handles = this.container.querySelectorAll('.crop-handle');
        handles.forEach(handle => {
            handle.addEventListener('mousedown', this.handleResizeStart.bind(this));
            handle.addEventListener('touchstart', this.handleResizeStart.bind(this), { passive: false });
        });
        
        this.cropBoxEl.addEventListener('mousedown', this.handleCropBoxMouseDown.bind(this));
        this.cropBoxEl.addEventListener('touchstart', this.handleCropBoxTouchStart.bind(this), { passive: false });
        
        this.zoomSlider.addEventListener('input', (e) => {
            this.setZoom(parseFloat(e.target.value));
            const percent = Math.round(parseFloat(e.target.value) * 100);
            this.container.querySelector('.zoom-value').textContent = percent + '%';
        });
        
        this.aspectRatioSelect.addEventListener('change', (e) => {
            const value = parseFloat(e.target.value);
            this.setAspectRatio(value === 0 ? null : value);
        });
        
        this.resetBtn.addEventListener('click', () => {
            this.reset();
        });
    }
    
    loadImage(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    this.image = img;
                    this.reset();
                    resolve(img);
                };
                img.onerror = reject;
                img.src = e.target.result;
            };
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }
    
    reset() {
        if (!this.image) return;
        
        this.zoom = 1;
        this.zoomSlider.value = 1;
        
        const containerWidth = this.canvas.parentElement.offsetWidth;
        const containerHeight = 400;
        
        const imageAspect = this.image.width / this.image.height;
        const containerAspect = containerWidth / containerHeight;
        
        let renderWidth, renderHeight;
        if (imageAspect > containerAspect) {
            renderHeight = containerHeight;
            renderWidth = renderHeight * imageAspect;
        } else {
            renderWidth = containerWidth;
            renderHeight = renderWidth / imageAspect;
        }
        
        this.canvas.width = containerWidth;
        this.canvas.height = containerHeight;
        
        this.imagePosition = {
            x: (containerWidth - renderWidth) / 2,
            y: (containerHeight - renderHeight) / 2
        };
        
        this.cropBox = {
            x: containerWidth * 0.1,
            y: containerHeight * 0.1,
            width: containerWidth * 0.8,
            height: (containerWidth * 0.8) / this.aspectRatio
        };
        
        if (this.cropBox.height > containerHeight * 0.8) {
            this.cropBox.height = containerHeight * 0.8;
            this.cropBox.width = this.cropBox.height * this.aspectRatio;
        }
        
        this.render();
    }
    
    render() {
        if (!this.image) return;
        
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        
        const scaledWidth = (this.image.width * this.zoom);
        const scaledHeight = (this.image.height * this.zoom);
        
        this.ctx.drawImage(
            this.image,
            this.imagePosition.x,
            this.imagePosition.y,
            scaledWidth,
            scaledHeight
        );
        
        this.updateCropBoxUI();
        
        if (this.onCropChange) {
            this.onCropChange(this.getCropData());
        }
    }
    
    updateCropBoxUI() {
        this.cropBoxEl.style.left = this.cropBox.x + 'px';
        this.cropBoxEl.style.top = this.cropBox.y + 'px';
        this.cropBoxEl.style.width = this.cropBox.width + 'px';
        this.cropBoxEl.style.height = this.cropBox.height + 'px';
        
        if (this.cropDimensionsEl) {
            const cropData = this.getCropData();
            if (cropData) {
                const width = Math.round(cropData.width);
                const height = Math.round(cropData.height);
                this.cropDimensionsEl.textContent = `${width} × ${height} px`;
            }
        }
    }
    
    handleCanvasMouseDown(e) {
        if (this.isResizing) return;
        this.isDragging = true;
        this.dragStart = {
            x: e.clientX - this.imagePosition.x,
            y: e.clientY - this.imagePosition.y
        };
    }
    
    handleCanvasMouseMove(e) {
        if (this.isDragging && !this.isResizing) {
            this.imagePosition = {
                x: e.clientX - this.dragStart.x,
                y: e.clientY - this.dragStart.y
            };
            this.render();
        }
    }
    
    handleCanvasMouseUp() {
        this.isDragging = false;
    }
    
    handleWheel(e) {
        e.preventDefault();
        const delta = e.deltaY > 0 ? -0.1 : 0.1;
        this.setZoom(this.zoom + delta);
    }
    
    handleTouchStart(e) {
        if (e.touches.length === 1 && !this.isResizing) {
            e.preventDefault();
            this.isDragging = true;
            this.dragStart = {
                x: e.touches[0].clientX - this.imagePosition.x,
                y: e.touches[0].clientY - this.imagePosition.y
            };
        }
    }
    
    handleTouchMove(e) {
        if (this.isDragging && e.touches.length === 1) {
            e.preventDefault();
            this.imagePosition = {
                x: e.touches[0].clientX - this.dragStart.x,
                y: e.touches[0].clientY - this.dragStart.y
            };
            this.render();
        }
    }
    
    handleTouchEnd() {
        this.isDragging = false;
    }
    
    handleCropBoxMouseDown(e) {
        if (e.target.classList.contains('crop-handle')) return;
        e.stopPropagation();
        this.isDraggingCropBox = true;
        const rect = this.cropBoxEl.getBoundingClientRect();
        this.cropBoxDragStart = {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top
        };
        
        const moveHandler = (e) => {
            if (this.isDraggingCropBox) {
                const containerRect = this.canvas.getBoundingClientRect();
                this.cropBox.x = Math.max(0, Math.min(
                    e.clientX - containerRect.left - this.cropBoxDragStart.x,
                    this.canvas.width - this.cropBox.width
                ));
                this.cropBox.y = Math.max(0, Math.min(
                    e.clientY - containerRect.top - this.cropBoxDragStart.y,
                    this.canvas.height - this.cropBox.height
                ));
                this.render();
            }
        };
        
        const upHandler = () => {
            this.isDraggingCropBox = false;
            document.removeEventListener('mousemove', moveHandler);
            document.removeEventListener('mouseup', upHandler);
        };
        
        document.addEventListener('mousemove', moveHandler);
        document.addEventListener('mouseup', upHandler);
    }
    
    handleCropBoxTouchStart(e) {
        if (e.target.classList.contains('crop-handle')) return;
        e.preventDefault();
        e.stopPropagation();
        this.isDraggingCropBox = true;
        const rect = this.cropBoxEl.getBoundingClientRect();
        this.cropBoxDragStart = {
            x: e.touches[0].clientX - rect.left,
            y: e.touches[0].clientY - rect.top
        };
        
        const moveHandler = (e) => {
            if (this.isDraggingCropBox && e.touches.length === 1) {
                const containerRect = this.canvas.getBoundingClientRect();
                this.cropBox.x = Math.max(0, Math.min(
                    e.touches[0].clientX - containerRect.left - this.cropBoxDragStart.x,
                    this.canvas.width - this.cropBox.width
                ));
                this.cropBox.y = Math.max(0, Math.min(
                    e.touches[0].clientY - containerRect.top - this.cropBoxDragStart.y,
                    this.canvas.height - this.cropBox.height
                ));
                this.render();
            }
        };
        
        const upHandler = () => {
            this.isDraggingCropBox = false;
            document.removeEventListener('touchmove', moveHandler);
            document.removeEventListener('touchend', upHandler);
        };
        
        document.addEventListener('touchmove', moveHandler, { passive: false });
        document.addEventListener('touchend', upHandler);
    }
    
    handleResizeStart(e) {
        e.preventDefault();
        e.stopPropagation();
        this.isResizing = true;
        this.resizeHandle = e.target.classList[1];
        
        const moveHandler = (e) => {
            if (this.isResizing) {
                this.handleResize(e.clientX || e.touches[0].clientX, e.clientY || e.touches[0].clientY);
            }
        };
        
        const upHandler = () => {
            this.isResizing = false;
            this.resizeHandle = null;
            document.removeEventListener('mousemove', moveHandler);
            document.removeEventListener('mouseup', upHandler);
            document.removeEventListener('touchmove', moveHandler);
            document.removeEventListener('touchend', upHandler);
        };
        
        document.addEventListener('mousemove', moveHandler);
        document.addEventListener('mouseup', upHandler);
        document.addEventListener('touchmove', moveHandler, { passive: false });
        document.addEventListener('touchend', upHandler);
    }
    
    handleResize(clientX, clientY) {
        const containerRect = this.canvas.getBoundingClientRect();
        const x = clientX - containerRect.left;
        const y = clientY - containerRect.top;
        
        let newBox = { ...this.cropBox };
        
        if (this.resizeHandle.includes('e')) {
            newBox.width = Math.max(this.minCropSize, x - this.cropBox.x);
        }
        if (this.resizeHandle.includes('w')) {
            const newWidth = Math.max(this.minCropSize, this.cropBox.x + this.cropBox.width - x);
            newBox.x = this.cropBox.x + this.cropBox.width - newWidth;
            newBox.width = newWidth;
        }
        if (this.resizeHandle.includes('s')) {
            newBox.height = Math.max(this.minCropSize, y - this.cropBox.y);
        }
        if (this.resizeHandle.includes('n')) {
            const newHeight = Math.max(this.minCropSize, this.cropBox.y + this.cropBox.height - y);
            newBox.y = this.cropBox.y + this.cropBox.height - newHeight;
            newBox.height = newHeight;
        }
        
        if (this.aspectRatio) {
            if (this.resizeHandle.includes('e') || this.resizeHandle.includes('w')) {
                newBox.height = newBox.width / this.aspectRatio;
            } else {
                newBox.width = newBox.height * this.aspectRatio;
            }
        }
        
        newBox.x = Math.max(0, Math.min(newBox.x, this.canvas.width - newBox.width));
        newBox.y = Math.max(0, Math.min(newBox.y, this.canvas.height - newBox.height));
        newBox.width = Math.min(newBox.width, this.canvas.width - newBox.x);
        newBox.height = Math.min(newBox.height, this.canvas.height - newBox.y);
        
        this.cropBox = newBox;
        this.render();
    }
    
    setZoom(value) {
        this.zoom = Math.max(this.minZoom, Math.min(this.maxZoom, value));
        this.zoomSlider.value = this.zoom;
        this.render();
    }
    
    setAspectRatio(ratio) {
        this.aspectRatio = ratio;
        if (ratio) {
            this.cropBox.height = this.cropBox.width / ratio;
            if (this.cropBox.y + this.cropBox.height > this.canvas.height) {
                this.cropBox.height = this.canvas.height - this.cropBox.y;
                this.cropBox.width = this.cropBox.height * ratio;
            }
        }
        this.render();
    }
    
    getCropData() {
        if (!this.image) return null;
        
        const scaleX = this.image.width / (this.image.width * this.zoom);
        const scaleY = this.image.height / (this.image.height * this.zoom);
        
        const cropX = (this.cropBox.x - this.imagePosition.x) * scaleX;
        const cropY = (this.cropBox.y - this.imagePosition.y) * scaleY;
        const cropWidth = this.cropBox.width * scaleX;
        const cropHeight = this.cropBox.height * scaleY;
        
        return {
            x: Math.max(0, cropX),
            y: Math.max(0, cropY),
            width: Math.min(cropWidth, this.image.width),
            height: Math.min(cropHeight, this.image.height)
        };
    }
    
    getCroppedCanvas(width = null, height = null) {
        if (!this.image) return null;
        
        const cropData = this.getCropData();
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        canvas.width = width || cropData.width;
        canvas.height = height || cropData.height;
        
        ctx.drawImage(
            this.image,
            cropData.x,
            cropData.y,
            cropData.width,
            cropData.height,
            0,
            0,
            canvas.width,
            canvas.height
        );
        
        return canvas;
    }
    
    getCroppedBlob(options = {}) {
        return new Promise((resolve) => {
            const canvas = this.getCroppedCanvas(options.width, options.height);
            if (!canvas) {
                resolve(null);
                return;
            }
            canvas.toBlob((blob) => {
                resolve(blob);
            }, options.type || 'image/jpeg', options.quality || 0.92);
        });
    }
    
    getElement() {
        return this.container;
    }
    
    destroy() {
        if (this.container && this.container.parentElement) {
            this.container.parentElement.removeChild(this.container);
        }
    }
}

window.ImageCropper = ImageCropper;
