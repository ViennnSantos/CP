// REPLACE THE ENTIRE FILE

const CM = {
    currentPage: 'home_public',
    currentContent: {},
    quillEditors: {},
    apiBaseUrl: '/backend/api/content_mgmt.php',
    previewUrl: '/backend/api/cms_preview.php',

    normalizePageKey(page) {
        // Any aliases you might use in tabs/dropdowns map to DB keys
        if (page === 'customer_home') return 'home_customer';
        return page;
    },

    init() {
        console.log('CM.init() called');

        // Check if preview iframe exists
        const previewIframe = document.getElementById('previewIframe');
        if (!previewIframe) {
            console.error('Preview iframe not found!');
            return;
        }

        const typeSelect = document.getElementById('homepageType');
        if (typeSelect && typeSelect.value) {
            this.currentPage = this.normalizePageKey(typeSelect.value);
        }

        console.log('Preview iframe found:', previewIframe);

        // Hide payment section initially
        const paymentSection = document.getElementById('paymentSettingsSection');
        if (paymentSection) {
            paymentSection.style.display = 'none';
        }

        this.setupEventListeners();
        this.loadPreview();
        console.log('CMS initialized successfully');
    },

    setupEventListeners() {
        // Homepage type selector
        const typeSelect = document.getElementById('homepageType');
        if (typeSelect) {
            typeSelect.addEventListener('change', (e) => {
                this.currentPage = e.target.value;
                this.updateActiveTab();
                this.loadPreview();
            });
        }

        // Tab switching
        document.querySelectorAll('.cm-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                const page = tab.dataset.page;
                this.switchTab(page);
            });
        });

        // Edit button
        const btnEdit = document.getElementById('btnEditContent');
        if (btnEdit) {
            btnEdit.addEventListener('click', () => this.openEditModal());
        }

        // Modal controls
        const btnClose = document.getElementById('btnCloseModal');
        if (btnClose) {
            btnClose.addEventListener('click', () => this.closeEditModal());
        }

        const btnSaveDraft = document.getElementById('btnSaveDraft');
        if (btnSaveDraft) {
            btnSaveDraft.addEventListener('click', () => this.saveDraft());
        }

        const btnPublish = document.getElementById('btnPublish');
        if (btnPublish) {
            btnPublish.addEventListener('click', () => this.publish());
        }

        const btnDiscard = document.getElementById('btnDiscard');
        if (btnDiscard) {
            btnDiscard.addEventListener('click', () => this.discardDraft());
        }
    },

    updateActiveTab() {
        document.querySelectorAll('.cm-tab').forEach(tab => {
            tab.classList.remove('active');
            if (tab.dataset.page === this.currentPage) {
                tab.classList.add('active');
            }
        });
    },

    switchTab(page) {
        console.log(`Switching to: ${page}`);
        this.currentPage = this.normalizePageKey(page);

        // Special handling for payment tab
        if (page === 'payment') {
            // Hide preview section
            const previewCard = document.getElementById('previewCard');
            if (previewCard) {
                previewCard.style.display = 'none';
            }

            // Hide Edit Content button
            const btnEdit = document.getElementById('btnEditContent');
            if (btnEdit) {
                btnEdit.style.display = 'none';
            }

            // Show payment settings section
            const paymentSection = document.getElementById('paymentSettingsSection');
            if (paymentSection) {
                paymentSection.style.display = 'block';
            }

            // Update active tab
            this.updateActiveTab();

            // Load payment QR data
            if (CM.Payment) {
                CM.Payment.loadPaymentQR();
            }

            return; // Don't load preview for payment tab
        }

        // For other tabs, show preview and hide payment section
        const previewCard = document.getElementById('previewCard');
        if (previewCard) {
            previewCard.style.display = 'block';
        }

        const btnEdit = document.getElementById('btnEditContent');
        if (btnEdit) {
            btnEdit.style.display = 'block';
        }

        const paymentSection = document.getElementById('paymentSettingsSection');
        if (paymentSection) {
            paymentSection.style.display = 'none';
        }

        // Update dropdown if switching to homepage
        const typeSelect = document.getElementById('homepageType');
        if (typeSelect && (page === 'home_public' || page === 'home_customer')) {
            typeSelect.value = page;
            typeSelect.parentElement.style.display = 'flex';
        } else if (typeSelect) {
            typeSelect.parentElement.style.display = 'none';
        }

        this.updateActiveTab();
        this.loadPreview();
    },

    loadPreview() {
        const iframe = document.getElementById('previewIframe');
        console.log('loadPreview() called, iframe:', iframe);

        if (!iframe) {
            console.error('Preview iframe not found in loadPreview()');
            return;
        }

        const key = this.normalizePageKey(this.currentPage);

        const url = `${this.previewUrl}?page=${this.currentPage}&t=${Date.now()}`;
        console.log('Loading preview URL:', url);

        iframe.src = url;
    },

    openEditModal() {
        console.log('Opening editor...');
        const modal = document.getElementById('editModal');
        if (!modal) return;

        modal.classList.add('show');

        // Update title
        const titles = {
            home_public: 'Edit Public Homepage',
            home_customer: 'Edit Customer Homepage',
            about: 'Edit About Us',
            privacy: 'Edit Privacy Policy',
            terms: 'Edit Terms & Conditions'
        };
        document.getElementById('modalTitle').textContent = titles[this.currentPage] || 'Edit Content';

        // Show customer notice for customer homepage
        const notice = document.getElementById('customerNotice');
        if (notice) {
            notice.style.display = this.currentPage === 'home_customer' ? 'block' : 'none';
        }

        // Build editor interface
        this.buildEditorInterface();

        // Load content
        this.loadEditorContent();
    },

    closeEditModal() {
        const modal = document.getElementById('editModal');
        if (modal) {
            modal.classList.remove('show');
        }
    },

    buildEditorInterface() {
        const container = document.getElementById('editorContainer');
        if (!container) return;

        if (this.currentPage === 'home_public') {
            container.innerHTML = this.getHomePublicEditor();
        } else if (this.currentPage === 'home_customer') {
            container.innerHTML = this.getHomeCustomerEditor();
        } else if (this.currentPage === 'about') {
            container.innerHTML = this.getAboutPageEditor();
        } else {
            container.innerHTML = this.getSimplePageEditor();
        }

        // Initialize Quill editors
        this.initQuillEditors();
    },

    getHomePublicEditor() {
        return `
            <!-- Hero Section -->
            <div class="editor-section">
                <h3><span class="material-symbols-rounded">home</span> Hero Section</h3>
                <label>Headline</label>
                <div id="quill-hero-headline" class="quill-container"></div>
                
                <label>Subtitle</label>
                <div id="quill-hero-subtitle" class="quill-container"></div>
            </div>

            <!-- Hero Media -->
            <label>Hero Media (image or .glb)</label>
              <input type="text" id="hero-image" class="form-input">
                <button type="button" class="btn-upload"
                onclick="document.getElementById('publicHeroUpload').click()">
            <span class="material-symbols-rounded">upload</span> Upload Hero
                </button>
            <input type="file" id="publicHeroUpload"
                accept="image/png,image/jpeg,image/webp,.glb,model/gltf-binary"
                style="display:none;" onchange="CM.handlePublicHeroUpload(event)">

            <!-- Carousel -->
            <div class="editor-section">
                <h3><span class="material-symbols-rounded">collections</span> Carousel Images</h3>
                <div id="carousel-manager"></div>
                <button type="button" class="btn-upload" onclick="document.getElementById('carouselUpload').click()">
                    <span class="material-symbols-rounded">add_photo_alternate</span> Add Image
                </button>
                <input type="file" id="carouselUpload" accept="image/*" style="display:none;" onchange="CM.handleCarouselUpload(event)">
            </div>

            <!-- Video -->
            <div class="editor-section">
                <h3><span class="material-symbols-rounded">videocam</span> Video Section</h3>
                <label>Video Title</label>
                <div id="quill-video-title" class="quill-container"></div>
                
                <label>Video Subtitle</label>
                <div id="quill-video-subtitle" class="quill-container"></div>
                
                <label>Video URL</label>
                <input type="text" id="video-url" class="form-input" placeholder="https://example.com/video.mp4">
                <button type="button" class="btn-upload" onclick="document.getElementById('videoUpload').click()">
                    <span class="material-symbols-rounded">upload</span> Upload Video
                </button>
                <input type="file" id="videoUpload" accept="video/*" style="display:none;" onchange="CM.handleVideoUpload(event)">
            </div>

            <!-- Footer -->
            <div class="editor-section">
                <h3><span class="material-symbols-rounded">contact_mail</span> Footer Contact Info</h3>
                <label>Company Name</label>
                <input type="text" id="footer-company" class="form-input">
                
                <label>Email</label>
                <input type="email" id="footer-email" class="form-input">
                
                <label>Phone</label>
                <input type="tel" id="footer-phone" class="form-input">
                
                <label>Address</label>
                <input type="text" id="footer-address" class="form-input">
                
                <label>Operating Hours</label>
                <input type="text" id="footer-hours" class="form-input">
            </div>
        `;
    },

    getHomeCustomerEditor() {
        return `
        <!-- Hero Section -->
        <div class="editor-section">
            <h3><span class="material-symbols-rounded">person</span> Hero Section</h3>
            
            <label>Welcome Message</label>
            <div id="quill-welcome" class="quill-container"></div>
            <p class="help-text">Use {{customer_name}} as placeholder for customer's name</p>
            
            <label>Introduction Text</label>
            <div id="quill-intro" class="quill-container"></div>
            
            <label>Hero Image</label>
            <input type="text" id="customer-hero-image" class="form-input" placeholder="/assets/images/cabinet-hero.jpg">
            <button type="button" class="btn-upload" onclick="document.getElementById('customerHeroUpload').click()">
                <span class="material-symbols-rounded">upload</span> Upload Hero Image
            </button>
            <input type="file" id="customerHeroUpload" accept="image/*" style="display:none;" onchange="CM.handleCustomerHeroUpload(event)">
        </div>

        <!-- Call-to-Action Buttons -->
        <div class="editor-section">
            <h3><span class="material-symbols-rounded">touch_app</span> CTA Buttons</h3>
            
            <label>Primary Button Text</label>
            <input type="text" id="cta-primary-text" class="form-input" placeholder="Start Designing">
            
            <label>Secondary Button Text</label>
            <input type="text" id="cta-secondary-text" class="form-input" placeholder="Browse Products">
        </div>

        <!-- Quick Actions Section -->
        <div class="editor-section">
            <h3><span class="material-symbols-rounded">dashboard</span> Quick Actions</h3>
            <p class="help-text">Quick Actions section is managed separately in Products Management</p>
        </div>

        <!-- Footer -->
        <div class="editor-section">
            <h3><span class="material-symbols-rounded">contact_mail</span> Footer Contact Info</h3>
            
            <label>Company Description</label>
            <textarea id="footer-description" class="form-input" rows="3" placeholder="Premium custom cabinet manufacturer..."></textarea>
            
            <label>Email</label>
            <input type="email" id="footer-email" class="form-input" placeholder="RadsTooling@gmail.com">
            
            <label>Phone</label>
            <input type="tel" id="footer-phone" class="form-input" placeholder="+63 976 228 4270">
            
            <label>Address</label>
            <input type="text" id="footer-address" class="form-input" placeholder="Green Breeze, Piela, Dasmariñas, Cavite">
            
            <label>Operating Hours</label>
            <input type="text" id="footer-hours" class="form-input" placeholder="Mon-Sat: 8:00 AM - 5:00 PM">
        </div>
    `;
    },

    getAboutPageEditor() {
        return `
        <!-- Background Image Section -->
        <div class="editor-section">
            <h3><span class="material-symbols-rounded">image</span> Hero Background Image</h3>
            
            <div id="about-hero-preview" class="image-preview-container" style="margin-bottom: 10px;">
                <img id="about-hero-img" src="" alt="Hero Background" style="max-width: 100%; height: 150px; object-fit: cover; border-radius: 4px; display: none;">
            </div>
            
            <button type="button" class="btn-upload" onclick="document.getElementById('aboutHeroUpload').click()">
                <span class="material-symbols-rounded">upload</span> Upload Hero Image
            </button>
            <input type="file" id="aboutHeroUpload" accept="image/*" style="display:none;" onchange="CM.handleAboutHeroUpload(event)">
            <input type="hidden" id="about-hero-path" value="">
        </div>

        <!-- Main Content -->
        <div class="editor-section">
            <h3><span class="material-symbols-rounded">description</span> About Section</h3>
            
            <label>Headline</label>
            <input type="text" id="about-headline" class="form-input" placeholder="About RADS Tooling">
            
            <label>Subheadline</label>
            <div id="quill-about-subheadline" class="quill-container"></div>
        </div>

        <!-- Mission & Vision -->
        <div class="editor-section">
            <h3><span class="material-symbols-rounded">flag</span> Mission & Vision</h3>
            
            <label>Our Mission</label>
            <div id="quill-about-mission" class="quill-container"></div>
            
            <label>Our Vision</label>
            <div id="quill-about-vision" class="quill-container"></div>
        </div>

        <!-- Our Story -->
        <div class="editor-section">
            <h3><span class="material-symbols-rounded">auto_stories</span> Our Story</h3>
            <div id="quill-about-story" class="quill-container"></div>
        </div>

        <!-- Store Information -->
        <div class="editor-section">
            <h3><span class="material-symbols-rounded">store</span> Store Information</h3>
            
            <label>Address</label>
            <input type="text" id="about-address" class="form-input" placeholder="Store address">
            
            <label>Phone</label>
            <input type="tel" id="about-phone" class="form-input" placeholder="+63 XXX XXX XXXX">
            
            <label>Email</label>
            <input type="email" id="about-email" class="form-input" placeholder="contact@example.com">
        </div>

        <!-- Operating Hours -->
        <div class="editor-section">
            <h3><span class="material-symbols-rounded">schedule</span> Operating Hours</h3>
            
            <label>Weekday Hours</label>
            <input type="text" id="about-hours-weekday" class="form-input" placeholder="Mon-Sat: 8:00 AM - 5:00 PM">
            
            <label>Sunday Hours</label>
            <input type="text" id="about-hours-sunday" class="form-input" placeholder="Sunday: Closed">
        </div>
    `;
    },

    getSimplePageEditor() {
        return `
        <div class="editor-section">
            <h3><span class="material-symbols-rounded">article</span> Page Content</h3>
            <label>Main Content</label>
            <div id="quill-main-content" class="quill-container" style="height: 450px; min-height: 450px;"></div>
        </div>
    `;
    },

    initQuillEditors() {
        this.quillEditors = {};

        const editorIds = [
            'quill-hero-headline',
            'quill-hero-subtitle',
            'quill-video-title',
            'quill-video-subtitle',
            'quill-welcome',
            'quill-intro',
            'quill-main-content',
            // ADD these for About page
            'quill-about-subheadline',
            'quill-about-mission',
            'quill-about-vision',
            'quill-about-story'
        ];

        editorIds.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                this.quillEditors[id] = new Quill(`#${id}`, {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            [{ 'header': [1, 2, 3, false] }],
                            ['bold', 'italic', 'underline'],
                            [{ 'color': [] }],
                            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                            ['link'],
                            ['clean']
                        ]
                    }
                });

                this.quillEditors[id].on('text-change', () => {
                    this.updateLivePreview();
                });
            }
        });
    },

    async loadEditorContent() {
        try {
            const response = await fetch(`${this.apiBaseUrl}?action=get&page=${this.currentPage}&status=draft`);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.success && data.content) {
                this.currentContent = data.content;
                this.populateFields(data.content);
                this.updateLivePreview();

                const statusEl = document.getElementById('previewStatus');
                if (statusEl) {
                    statusEl.textContent = data.status === 'draft' ? 'Draft' : 'Published';
                    statusEl.className = 'preview-status ' + (data.status === 'draft' ? 'status-draft' : 'status-published');
                }
            } else {
                throw new Error(data.message || 'Failed to load content');
            }
        } catch (error) {
            console.error('Error loading content:', error);
            this.showToast('Error loading content: ' + error.message, 'error');
        }
    },

    populateFields(content) {
        // Populate Quill editors
        if (this.quillEditors['quill-hero-headline'] && content.hero_headline) {
            this.quillEditors['quill-hero-headline'].root.innerHTML = content.hero_headline;
        }
        if (this.quillEditors['quill-hero-subtitle'] && content.hero_subtitle) {
            this.quillEditors['quill-hero-subtitle'].root.innerHTML = content.hero_subtitle;
        }
        if (this.quillEditors['quill-video-title'] && content.video_title) {
            this.quillEditors['quill-video-title'].root.innerHTML = content.video_title;
        }
        if (this.quillEditors['quill-video-subtitle'] && content.video_subtitle) {
            this.quillEditors['quill-video-subtitle'].root.innerHTML = content.video_subtitle;
        }
        if (this.quillEditors['quill-welcome'] && content.welcome_message) {
            this.quillEditors['quill-welcome'].root.innerHTML = content.welcome_message;
        }
        if (this.quillEditors['quill-intro'] && content.intro_text) {
            this.quillEditors['quill-intro'].root.innerHTML = content.intro_text;
        }

        // FIX: Handle simple page content
        if (this.quillEditors['quill-main-content'] && content.content) {
            this.quillEditors['quill-main-content'].root.innerHTML = content.content;
        }

        if (this.currentPage === 'home_customer') {
            // Populate additional customer homepage fields
            const customerInputs = {
                'customer-hero-image': content.hero_image,
                'cta-primary-text': content.cta_primary_text,
                'cta-secondary-text': content.cta_secondary_text,
                'footer-description': content.footer_description,
                'footer-email': content.footer_email,
                'footer-phone': content.footer_phone,
                'footer-address': content.footer_address,
                'footer-hours': content.footer_hours
            };

            const publicHero = document.getElementById('hero-image');
            if (publicHero && content.hero_image) {
                publicHero.value = content.hero_image;
                publicHero.addEventListener('input', () => this.updateLivePreview());
            }

            Object.keys(customerInputs).forEach(id => {
                const input = document.getElementById(id);
                if (input && customerInputs[id]) {
                    input.value = customerInputs[id];
                    input.addEventListener('input', () => this.updateLivePreview());
                }
            });
        }

        if (this.currentPage === 'about') {
            // Text inputs
            const aboutInputs = {
                'about-headline': content.about_headline,
                'about-address': content.about_address,
                'about-phone': content.about_phone,
                'about-email': content.about_email,
                'about-hours-weekday': content.about_hours_weekday,
                'about-hours-sunday': content.about_hours_sunday,
                'footer-description': content.footer_description,
                'footer-email': content.footer_email,
                'footer-phone': content.footer_phone,
                'footer-address': content.footer_address,
                'footer-hours': content.footer_hours
            };

            Object.keys(aboutInputs).forEach(id => {
                const input = document.getElementById(id);
                if (input && aboutInputs[id]) {
                    input.value = aboutInputs[id];
                    input.addEventListener('input', () => this.updateLivePreview());
                }
            });

            // Hero image
            if (content.about_hero_image) {
                const heroImg = document.getElementById('about-hero-img');
                const heroPath = document.getElementById('about-hero-path');
                if (heroImg && heroPath) {
                    heroPath.value = content.about_hero_image;
                    heroImg.src = content.about_hero_image;
                    heroImg.style.display = 'block';
                }
            }

            // Quill editors
            if (this.quillEditors['quill-about-subheadline'] && content.about_subheadline) {
                this.quillEditors['quill-about-subheadline'].root.innerHTML = content.about_subheadline;
            }
            if (this.quillEditors['quill-about-mission'] && content.about_mission) {
                this.quillEditors['quill-about-mission'].root.innerHTML = content.about_mission;
            }
            if (this.quillEditors['quill-about-vision'] && content.about_vision) {
                this.quillEditors['quill-about-vision'].root.innerHTML = content.about_vision;
            }
            if (this.quillEditors['quill-about-story'] && content.about_story) {
                this.quillEditors['quill-about-story'].root.innerHTML = content.about_story;
            }
        }

        // Populate form inputs
        const inputs = {
            'footer-company': content.footer_company,
            'footer-email': content.footer_email,
            'footer-phone': content.footer_phone,
            'footer-address': content.footer_address,
            'footer-hours': content.footer_hours,
            'video-url': content.video_url
        };

        Object.keys(inputs).forEach(id => {
            const input = document.getElementById(id);
            if (input && inputs[id]) {
                input.value = inputs[id];
                input.addEventListener('input', () => this.updateLivePreview());
            }
        });

        // Render carousel
        if (content.carousel_images) {
            this.renderCarousel(content.carousel_images);
        }
    },

    renderCarousel(images) {
        const container = document.getElementById('carousel-manager');
        if (!container) return;

        container.innerHTML = images.map((img, i) => `
            <div class="carousel-item-edit">
                <img src="${img.image}" alt="${img.title}">
                <div class="carousel-info">
                    <input type="text" value="${img.title}" onchange="CM.updateCarouselItem(${i}, 'title', this.value)">
                    <input type="text" value="${img.description}" onchange="CM.updateCarouselItem(${i}, 'description', this.value)">
                </div>
                <div class="carousel-actions">
                    <button type="button" onclick="CM.moveCarousel(${i}, -1)" ${i === 0 ? 'disabled' : ''}>
                        <span class="material-symbols-rounded">arrow_upward</span>
                    </button>
                    <button type="button" onclick="CM.moveCarousel(${i}, 1)" ${i === images.length - 1 ? 'disabled' : ''}>
                        <span class="material-symbols-rounded">arrow_downward</span>
                    </button>
                    <button type="button" class="btn-delete" onclick="CM.deleteCarouselItem(${i})">
                        <span class="material-symbols-rounded">delete</span>
                    </button>
                </div>
            </div>
        `).join('');
    },

    updateCarouselItem(index, field, value) {
        if (!this.currentContent.carousel_images) return;
        this.currentContent.carousel_images[index][field] = value;
        this.updateLivePreview();
    },

    moveCarousel(index, direction) {
        const images = this.currentContent.carousel_images;
        if (!images) return;

        const newIndex = index + direction;
        if (newIndex < 0 || newIndex >= images.length) return;

        [images[index], images[newIndex]] = [images[newIndex], images[index]];
        this.renderCarousel(images);
        this.updateLivePreview();
    },

    deleteCarouselItem(index) {
        if (!confirm('Delete this image?')) return;
        this.currentContent.carousel_images.splice(index, 1);
        this.renderCarousel(this.currentContent.carousel_images);
        this.updateLivePreview();
    },

    async handleCarouselUpload(event) {
        const file = event.target.files[0];
        if (!file) return;

        // Validate file type
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            this.showToast('Please upload a valid image (JPG, PNG, or WebP)', 'error');
            event.target.value = '';
            return;
        }

        // Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            this.showToast('Image must be less than 5MB', 'error');
            event.target.value = '';
            return;
        }

        const formData = new FormData();
        formData.append('image', file);
        formData.append('group', 'carousel');
        formData.append('action', 'upload_image');

        try {
            this.showToast('Uploading image...', 'info');

            const response = await fetch(this.apiBaseUrl, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                if (!this.currentContent.carousel_images) {
                    this.currentContent.carousel_images = [];
                }

                this.currentContent.carousel_images.push({
                    image: '/' + data.file_path.replace(/^\/+/, ''),
                    title: 'New Image',
                    description: 'Add description'
                });

                this.renderCarousel(this.currentContent.carousel_images);
                this.updateLivePreview();
                this.showToast('Image uploaded successfully!', 'success');
            } else {
                throw new Error(data.message || 'Upload failed');
            }
        } catch (error) {
            console.error('Upload error:', error);
            this.showToast('Upload failed: ' + error.message, 'error');
        } finally {
            event.target.value = '';
        }
    },

    async handleVideoUpload(event) {
        const file = event.target.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('image', file);
        formData.append('group', 'videos');

        try {
            this.showToast('Uploading video...', 'info');
            const response = await fetch(`${this.apiBaseUrl}?action=upload_image`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (data.success) {
                const input = document.getElementById('video-url');
                if (input) {
                    input.value = data.file_path;
                    this.updateLivePreview();
                }
                this.showToast('Video uploaded', 'success');
                event.target.value = '';
            }
        } catch (error) {
            console.error('Upload error:', error);
            this.showToast('Upload failed', 'error');
        }
    },

    async handleAboutHeroUpload(event) {
        const file = event.target.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('image', file);
        formData.append('group', 'about');
        formData.append('action', 'upload_image');

        try {
            this.showToast('Uploading...', 'info');
            const response = await fetch(this.apiBaseUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (data.success) {
                const imgPath = '/' + data.file_path.replace(/^\/+/, '');
                document.getElementById('about-hero-path').value = imgPath;
                document.getElementById('about-hero-img').src = imgPath;
                document.getElementById('about-hero-img').style.display = 'block';
                this.updateLivePreview();
                this.showToast('Image uploaded!', 'success');
            }
        } catch (error) {
            console.error('Upload error:', error);
            this.showToast('Upload failed', 'error');
        } finally {
            event.target.value = '';
        }
    },

    async handleCustomerHeroUpload(event) {
        const file = event.target.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('image', file);
        formData.append('group', 'customer');
        formData.append('action', 'upload_image');

        try {
            this.showToast('Uploading...', 'info');
            const response = await fetch(this.apiBaseUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (data.success) {
                const imgPath = '/' + data.file_path.replace(/^\/+/, '');
                document.getElementById('customer-hero-image').value = imgPath;
                this.updateLivePreview();
                this.showToast('Image uploaded!', 'success');
            }
        } catch (error) {
            console.error('Upload error:', error);
            this.showToast('Upload failed', 'error');
        } finally {
            event.target.value = '';
        }
    },

    async handlePublicHeroUpload(event) {
        const file = event.target.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('image', file);
        formData.append('group', 'hero');
        formData.append('action', 'upload_image');

        try {
            this.showToast('Uploading...', 'info');
            const response = await fetch(this.apiBaseUrl, { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) {
                const path = '/' + data.file_path.replace(/^\/+/, '');
                document.getElementById('hero-image').value = path;
                this.updateLivePreview();
                this.showToast('Uploaded!', 'success');
            } else {
                throw new Error(data.message || 'Upload failed');
            }
        } catch (err) {
            console.error(err);
            this.showToast('Upload failed', 'error');
        } finally {
            event.target.value = '';
        }
    },

    updateLivePreview() {
        // Reload the live preview iframe
        const iframe = document.getElementById('livePreviewIframe');
        if (!iframe) return;

        const key = this.normalizePageKey(this.currentPage);

        // Reload with current page and timestamp to avoid cache
        iframe.src = `${this.previewUrl}?page=${this.currentPage}&t=${Date.now()}`;
    },

    collectContent() {
        const content = { ...this.currentContent };

        // Collect from Quill editors
        Object.keys(this.quillEditors).forEach(key => {
            const editor = this.quillEditors[key];
            const html = editor.root.innerHTML;

            // Map editor IDs to content field names
            if (key === 'quill-hero-headline') content.hero_headline = html;
            else if (key === 'quill-hero-subtitle') content.hero_subtitle = html;
            else if (key === 'quill-video-title') content.video_title = html;
            else if (key === 'quill-video-subtitle') content.video_subtitle = html;
            else if (key === 'quill-welcome') content.welcome_message = html;
            else if (key === 'quill-intro') content.intro_text = html;
            else if (key === 'quill-main-content') content.content = html; // FIX: Map to 'content'
            else if (key === 'quill-about-subheadline') content.about_subheadline = html;
            else if (key === 'quill-about-mission') content.about_mission = html;
            else if (key === 'quill-about-vision') content.about_vision = html;
            else if (key === 'quill-about-story') content.about_story = html;
        });

        // Collect from inputs
        const inputs = ['footer-company', 'footer-email', 'footer-phone', 'footer-address', 'footer-hours', 'video-url',
            'about-headline', 'about-address', 'about-phone', 'about-email',
            'about-hours-weekday', 'about-hours-sunday', 'about-hero-path', 'customer-hero-image', 'cta-primary-text', 'cta-secondary-text',
            'footer-description', 'hero-image'
        ];
        inputs.forEach(id => {
            const input = document.getElementById(id);
            if (input) {
                const fieldName = id.replace(/-/g, '_');
                content[fieldName] = input.value;
            }
        });

        return content;
    },

    async saveDraft() {
        const content = this.collectContent();

        const formData = new FormData();
        formData.append('action', 'save');
        formData.append('page', this.currentPage);
        formData.append('content', JSON.stringify(content));

        try {
            this.showToast('Saving draft...', 'info');

            const response = await fetch(this.apiBaseUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                if (data.unchanged) {
                    this.showToast('No changes to save', 'info');
                } else {
                    this.showToast('Draft saved!', 'success');

                    // CRITICAL: Wait then force hard reload of BOTH previews
                    setTimeout(() => {
                        const timestamp = Date.now();
                        const random = Math.random().toString(36).substring(7);
                        const previewUrl = `${this.previewUrl}?page=${this.currentPage}&t=${timestamp}&r=${random}`;

                        // 1. Reload modal preview (Live Preview inside edit modal)
                        const modalPreview = document.getElementById('livePreviewIframe');
                        if (modalPreview) {
                            // Force complete reload by setting to blank first
                            const oldSrc = modalPreview.src;
                            modalPreview.src = 'about:blank';

                            setTimeout(() => {
                                modalPreview.src = previewUrl;
                                console.log('✅ Modal preview reloaded:', previewUrl);
                            }, 100);
                        }

                        // 2. Reload main preview (Content Management page)
                        const mainPreview = document.getElementById('previewIframe');
                        if (mainPreview) {
                            const oldSrc = mainPreview.src;
                            mainPreview.src = 'about:blank';

                            setTimeout(() => {
                                mainPreview.src = previewUrl;
                                console.log('✅ Main preview reloaded:', previewUrl);
                            }, 100);
                        }
                    }, 300); // Wait 300ms for database commit

                    // Update status badge
                    const statusEl = document.getElementById('previewStatus');
                    if (statusEl) {
                        statusEl.textContent = 'Draft';
                        statusEl.className = 'preview-status status-draft';
                    }
                }
            } else {
                throw new Error(data.message || 'Save failed');
            }
        } catch (error) {
            console.error('Save error:', error);
            this.showToast('Error saving draft: ' + error.message, 'error');
        }
    },

    async publish() {
        // CRITICAL: Check if draft exists before allowing publish
        try {
            const checkResponse = await fetch(`${this.apiBaseUrl}?action=get&page=${this.currentPage}&status=draft`);
            const checkData = await checkResponse.json();

            if (!checkData.success || checkData.status !== 'draft') {
                this.showToast('You must save a draft before publishing!', 'error');
                return;
            }
        } catch (error) {
            this.showToast('Error checking draft status', 'error');
            return;
        }

        if (!confirm('Publish this content? It will be visible to all users.')) return;

        const formData = new FormData();
        formData.append('action', 'publish');
        formData.append('page', this.currentPage);

        try {
            this.showToast('Publishing...', 'info');
            const response = await fetch(this.apiBaseUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (data.success) {
                this.showToast('Content published!', 'success');

                // Reload preview to show published status
                setTimeout(() => {
                    const timestamp = Date.now();
                    const mainPreview = document.getElementById('previewIframe');
                    if (mainPreview) {
                        mainPreview.src = `${this.previewUrl}?page=${this.currentPage}&t=${timestamp}`;
                    }
                }, 300);

                this.closeEditModal();
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Publish error:', error);
            this.showToast('Error publishing', 'error');
        }
    },

    async discardDraft() {
        if (!confirm('Discard all unsaved changes? This will revert to the published version.')) return;

        const formData = new FormData();
        formData.append('action', 'discard');
        formData.append('page', this.currentPage);

        try {
            this.showToast('Discarding changes...', 'info');
            const response = await fetch(this.apiBaseUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (data.success) {
                this.showToast('Changes discarded', 'info');
                this.loadEditorContent(); // Reload published content
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Discard error:', error);
            this.showToast('Error discarding', 'error');
        }
    },

    showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast toast-${type} show`;
        toast.textContent = message;

        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
};

// BETTER initialization approach
document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM Ready - Initializing CMS');

    // Check if Content Management section exists
    const contentSection = document.querySelector('[data-section="content"]');
    if (!contentSection) {
        console.log('Content section not found');
        return;
    }

    // If content section is already visible, init immediately
    if (contentSection.classList.contains('show')) {
        console.log('Content section visible - initializing now');
        CM.init();
    }

    // Watch for navigation clicks
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', function () {
            const targetSection = this.dataset.section;
            console.log('Navigation clicked:', targetSection);

            if (targetSection === 'content') {
                // Wait for section to be visible
                setTimeout(() => {
                    console.log('Initializing CMS after navigation');
                    CM.init();
                }, 300);
            }
            
        });
        
    });
    CM.Payment = {
    currentQRData: {},
    apiUrl: '/backend/api/content_mgmt.php',

    init() {
        console.log('Payment QR Management initialized');
        this.setupEventListeners();
        this.loadPaymentQR();
    },

    setupEventListeners() {
    const gcashUpload = document.getElementById('gcashQRUpload');
    if (gcashUpload) {
        gcashUpload.addEventListener('change', (e) => {
            this.handleQRUpload('gcash', e.target.files[0]);
        });
    } else {
        console.warn('gcashQRUpload element not found');
    }

    const bpiUpload = document.getElementById('bpiQRUpload');
    if (bpiUpload) {
        bpiUpload.addEventListener('change', (e) => {
            this.handleQRUpload('bpi', e.target.files[0]);
        });
    } else {
        console.warn('bpiQRUpload element not found');
    }

    const btnGCashUpload = document.getElementById('btnGCashUpload');
    if (btnGCashUpload && gcashUpload) {
        btnGCashUpload.addEventListener('click', () => gcashUpload.click());
    }

    const btnBPIUpload = document.getElementById('btnBPIUpload');
    if (btnBPIUpload && bpiUpload) {
        btnBPIUpload.addEventListener('click', () => bpiUpload.click());
    }
},


    async loadPaymentQR() {
    try {
        const response = await fetch(`${this.apiUrl}?action=get_payment_qr`);
        if (!response.ok) throw new Error('HTTP ' + response.status);
        const result = await response.json();

        if (result && result.success && result.data) {
            this.currentQRData = result.data;
            this.displayQRCodes();
        } else {
            console.warn('Unexpected QR response:', result);
            this.currentQRData = {};
            this.displayQRCodes();
        }
    } catch (err) {
        console.error('Error loading payment QR:', err);
        this.showToast('Failed to load QR codes', 'error');
    }
},


    // ═══════════════════════════════════════════════════════════════
// FIXED: displayQRCodes() function with better error handling
// Replace lines 1201-1226 in your content_mgmt.js with this:
// ═══════════════════════════════════════════════════════════════

displayQRCodes() {
    console.log('📸 displayQRCodes called, currentQRData:', this.currentQRData);
    
    const buildPath = (path) => {
        console.log('🔧 buildPath input:', path);
        
        if (!path) {
            console.warn('⚠️  Empty path provided');
            return '';
        }
        
        // If already a full URL, return as-is
        if (/^https?:\/\//i.test(path)) {
            console.log('✅ Full URL detected:', path);
            return path;
        }
        
        // Remove leading slashes
        const trimmed = path.replace(/^\/+/, '');
        console.log('🔄 Trimmed path:', trimmed);
        
        // If already starts with RADS-TOOLING, just add leading slash
        if (/^RADS-TOOLING\//.test(trimmed)) {
            const result = '/' + trimmed;
            console.log('✅ Path with RADS-TOOLING prefix:', result);
            return result;
        }
        
        // Otherwise, prepend /
        const result = '/' + trimmed;
        console.log('✅ Built final path:', result);
        return result;
    };

    const gcashPreview = document.getElementById('gcashQRPreview');
    const bpiPreview = document.getElementById('bpiQRPreview');

    // ═══ GCASH QR ═══
    console.log('💳 Processing GCash QR...');
    if (this.currentQRData.gcash?.image_path) {
        console.log('✅ GCash data found:', this.currentQRData.gcash);
        const imgPath = buildPath(this.currentQRData.gcash.image_path);
        
        if (gcashPreview) {
            gcashPreview.innerHTML = `<img 
                src="${imgPath}" 
                alt="GCash QR" 
                style="max-width:100%;max-height:200px;border-radius:8px;object-fit:contain;"
                onerror="console.error('❌ Failed to load GCash QR:', this.src); this.parentElement.innerHTML='<div class=\\'qr-placeholder\\' style=\\'color:red;\\'>Failed to load QR image</div>';"
                onload="console.log('✅ GCash QR loaded successfully:', this.src);">`;
            console.log('✅ GCash QR HTML set, path:', imgPath);
        } else {
            console.error('❌ gcashQRPreview element not found!');
        }
    } else {
        console.warn('⚠️  No GCash QR data available');
        if (gcashPreview) {
            gcashPreview.innerHTML = `<div class="qr-placeholder">No GCash QR uploaded</div>`;
        }
    }

    // ═══ BPI QR ═══
    console.log('🏦 Processing BPI QR...');
    if (this.currentQRData.bpi?.image_path) {
        console.log('✅ BPI data found:', this.currentQRData.bpi);
        const imgPath = buildPath(this.currentQRData.bpi.image_path);
        
        if (bpiPreview) {
            bpiPreview.innerHTML = `<img 
                src="${imgPath}" 
                alt="BPI QR" 
                style="max-width:100%;max-height:200px;border-radius:8px;object-fit:contain;"
                onerror="console.error('❌ Failed to load BPI QR:', this.src); this.parentElement.innerHTML='<div class=\\'qr-placeholder\\' style=\\'color:red;\\'>Failed to load QR image</div>';"
                onload="console.log('✅ BPI QR loaded successfully:', this.src);">`;
            console.log('✅ BPI QR HTML set, path:', imgPath);
        } else {
            console.error('❌ bpiQRPreview element not found!');
        }
    } else {
        console.warn('⚠️  No BPI QR data available');
        if (bpiPreview) {
            bpiPreview.innerHTML = `<div class="qr-placeholder">No BPI QR uploaded</div>`;
        }
    }
    
    console.log('📸 displayQRCodes completed');
},

    async handleQRUpload(method, file) {
        if (!file) {
            this.showToast('Please select a file', 'error');
            return;
        }

        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            this.showToast('Invalid file type. Please upload JPG, PNG, GIF, or WEBP', 'error');
            return;
        }

        // Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            this.showToast('File too large. Maximum size is 5MB', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'update_payment_qr');
        formData.append('method', method);
        formData.append('qr_image', file);

        try {
            // Show loading state
            const uploadBtn = method === 'gcash' ? 
                document.getElementById('btnGCashUpload') : 
                document.getElementById('btnBPIUpload');
            
            if (uploadBtn) {
                uploadBtn.disabled = true;
                uploadBtn.innerHTML = '<span class="material-symbols-rounded">hourglass_empty</span> Uploading...';
            }

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showToast(`${method.toUpperCase()} QR code updated successfully!`, 'success');
                
                // Reload QR codes to show new one
                await this.loadPaymentQR();
            } else {
                throw new Error(result.message || 'Upload failed');
            }
        } catch (error) {
            console.error('Error uploading QR:', error);
            this.showToast('Failed to upload QR code: ' + error.message, 'error');
        } finally {
            // Reset button state
            const uploadBtn = method === 'gcash' ? 
                document.getElementById('btnGCashUpload') : 
                document.getElementById('btnBPIUpload');
            
            if (uploadBtn) {
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = '<span class="material-symbols-rounded">upload</span> Upload QR';
            }
        }
    },

    showPaymentSettings() {
        // Hide all editor sections
        document.querySelectorAll('.editor-section').forEach(section => {
            section.style.display = 'none';
        });

        // Show payment section
        const paymentSection = document.getElementById('paymentSettingsSection');
        if (paymentSection) {
            paymentSection.style.display = 'block';
        }

        // Update active tab
        document.querySelectorAll('.cm-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelector('.cm-tab[data-page="payment"]')?.classList.add('active');

        // Load payment QR data
        this.loadPaymentQR();
    },

    showToast(message, type = 'info') {
        // Create toast container if it doesn't exist
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        // Create toast
        const toast = document.createElement('div');
        toast.className = `toast toast-${type} show`;
        toast.textContent = message;

        container.appendChild(toast);

        // Auto remove after 3 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
};

// Initialize payment module when CM initializes
document.addEventListener('DOMContentLoaded', function() {
    if (typeof CM !== 'undefined' && CM.Payment) {
        CM.Payment.init();
    }
});
    
});

(function() {
    'use strict';

    console.log('💳 Payment QR Management initialized');

    // ========== 1. TAB SWITCHING ==========
    // Handle payment tab click
    function initPaymentTab() {
        const tabs = document.querySelectorAll('.cm-tab');
        const paymentSection = document.getElementById('paymentSettingsSection');
        const previewCard = document.getElementById('previewCard');

        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const page = this.getAttribute('data-page');
                
                // Remove active from all tabs
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                console.log('📑 Tab clicked:', page);

                if (page === 'payment') {
                    // Show payment QR section, hide preview
                    if (paymentSection) paymentSection.style.display = 'block';
                    if (previewCard) previewCard.style.display = 'none';
                    
                    // Load current QR codes
                    loadPaymentQRCodes();
                } else {
                    // Hide payment section, show preview
                    if (paymentSection) paymentSection.style.display = 'none';
                    if (previewCard) previewCard.style.display = 'block';
                }
            });
        });
    }

    // ========== 2. LOAD CURRENT QR CODES ==========
    async function loadPaymentQRCodes() {
        console.log('🔄 Loading payment QR codes...');

        try {
            const response = await fetch('/backend/api/content_mgmt.php?action=get_payment_qr', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });

            console.log('📡 Response status:', response.status);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            console.log('📦 QR data received:', data);

            if (data.success) {
                // Display GCash QR
                displayQRCode('gcash', data.data.gcash);
                // Display BPI QR
                displayQRCode('bpi', data.data.bpi);
            } else {
                console.error('❌ Failed to load QR codes:', data.message);
                showToast('error', 'Failed to load QR codes');
            }

        } catch (error) {
            console.error('💥 Error loading QR codes:', error);
            showToast('error', 'Network error loading QR codes');
        }
    }

    // Display QR code in preview area
    function displayQRCode(method, qrData) {
        const previewId = method === 'gcash' ? 'gcashQRPreview' : 'bpiQRPreview';
        const preview = document.getElementById(previewId);
        
        if (!preview) {
            console.error(`Preview element not found: ${previewId}`);
            return;
        }

        if (qrData && qrData.image_path) {
            // Build full URL with / prefix
            const imageUrl = `/${qrData.image_path}`;
            
            console.log(`✅ ${method.toUpperCase()} QR found:`, imageUrl);
            
            // Create image element
            preview.innerHTML = `
                <img 
                    src="${imageUrl}?v=${Date.now()}" 
                    alt="${method.toUpperCase()} QR Code" 
                    style="max-width: 100%; max-height: 300px; object-fit: contain; border-radius: 8px;"
                    onerror="this.parentElement.innerHTML='<span style=\\'color: #e74c3c;\\'>❌ Failed to load QR image</span>'"
                />
            `;
        } else {
            console.log(`ℹ️ No ${method.toUpperCase()} QR uploaded yet`);
            preview.innerHTML = '<span style="color: #999;">No QR code uploaded yet</span>';
        }
    }

    // ========== 3. UPLOAD HANDLERS ==========
    function initUploadHandlers() {
        // GCash Upload
        const btnGCash = document.getElementById('btnGCashUpload');
        const inputGCash = document.getElementById('gcashQRUpload');

        if (btnGCash && inputGCash) {
            btnGCash.addEventListener('click', () => {
                inputGCash.click();
            });

            inputGCash.addEventListener('change', (e) => {
                if (e.target.files && e.target.files[0]) {
                    uploadQRCode('gcash', e.target.files[0]);
                }
            });
        }

        // BPI Upload
        const btnBPI = document.getElementById('btnBPIUpload');
        const inputBPI = document.getElementById('bpiQRUpload');

        if (btnBPI && inputBPI) {
            btnBPI.addEventListener('click', () => {
                inputBPI.click();
            });

            inputBPI.addEventListener('change', (e) => {
                if (e.target.files && e.target.files[0]) {
                    uploadQRCode('bpi', e.target.files[0]);
                }
            });
        }

        console.log('📤 Upload handlers initialized');
    }

    // ========== 4. UPLOAD QR CODE TO BACKEND ==========
    async function uploadQRCode(method, file) {
        // Validate file size (max 5MB)
        const maxSize = 5 * 1024 * 1024; // 5MB in bytes
        if (file.size > maxSize) {
            showToast('error', 'File too large! Maximum size is 5MB.');
            return;
        }

        // Validate file type
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            showToast('error', 'Invalid file type! Only JPG, PNG, GIF, and WEBP are allowed.');
            return;
        }

        console.log(`📤 Uploading ${method.toUpperCase()} QR:`, file.name);
        showToast('info', `Uploading ${method.toUpperCase()} QR code...`);

        const formData = new FormData();
        formData.append('method', method);
        formData.append('qr_image', file);
        formData.append('action', 'update_payment_qr');

        try {
            const response = await fetch('/backend/api/content_mgmt.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });

            console.log('📡 Upload response status:', response.status);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            console.log('📦 Upload result:', data);

            if (data.success) {
                showToast('success', `${method.toUpperCase()} QR code uploaded successfully!`);
                
                // Refresh QR codes display
                await loadPaymentQRCodes();
                
                // Clear file input
                const inputId = method === 'gcash' ? 'gcashQRUpload' : 'bpiQRUpload';
                const input = document.getElementById(inputId);
                if (input) input.value = '';
                
            } else {
                showToast('error', data.message || 'Upload failed');
            }

        } catch (error) {
            console.error('💥 Upload error:', error);
            showToast('error', 'Failed to upload QR code. Please try again.');
        }
    }

    // ========== 5. NOTIFICATION HELPER ==========
    function showToast(type, message) {
        console.log(`🔔 Toast [${type}]:`, message);

        // Try to use existing notification system
        if (typeof showNotification === 'function') {
            showNotification(type, message);
            return;
        }

        // Fallback: create simple toast
        const container = document.getElementById('toastContainer') || createToastContainer();
        
        const toast = document.createElement('div');
        toast.className = `toast show toast-${type}`;
        toast.textContent = message;
        
        // Color based on type
        const colors = {
            success: '#28a745',
            error: '#dc3545',
            info: '#0dcaf0',
            warning: '#ffc107'
        };
        toast.style.cssText = `
            background: ${colors[type] || colors.info};
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-weight: 500;
            animation: slideIn 0.3s ease;
        `;
        
        container.appendChild(toast);
        
        // Auto remove after 3.5 seconds
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-20px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }

    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toastContainer';
        container.style.cssText = `
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        `;
        document.body.appendChild(container);
        return container;
    }

    // ========== 6. INITIALIZE ON PAGE LOAD ==========
    function init() {
        console.log('🚀 Initializing Payment QR Management...');
        
        // Initialize tab switching
        initPaymentTab();
        
        // Initialize upload button handlers
        initUploadHandlers();
        
        // Load QR codes if payment tab is active
        const activeTab = document.querySelector('.cm-tab.active');
        if (activeTab && activeTab.getAttribute('data-page') === 'payment') {
            loadPaymentQRCodes();
        }

        console.log('✅ Payment QR Management ready!');
    }

    // Run init when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

// ============================================
// END OF PAYMENT QR MANAGEMENT CODE
// ============================================

console.log('💳 Payment QR script loaded!');