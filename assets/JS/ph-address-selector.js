/**
 * ph-address-selector.js
 * Cascading dropdown logic for Philippine Regions, Provinces, Cities, and Barangays.
 * Data source: https://github.com/isaacdarcilla/philippine-addresses
 */

const PH_ADDRESS_BASE_URL = 'https://isaacdarcilla.github.io/philippine-addresses';

class PHAddressSelector {
    constructor(config) {
        this.prefix = config.prefix || ''; // e.g., 'home-' or 'ec-'
        this.selectors = {
            region: document.getElementById(`${this.prefix}region`),
            province: document.getElementById(`${this.prefix}province`),
            city: document.getElementById(`${this.prefix}city`),
            barangay: document.getElementById(`${this.prefix}barangay`)
        };

        this.data = {
            regions: [],
            provinces: [],
            cities: [],
            barangays: []
        };

        this.init();
    }

    async init() {
        try {
            // Load Regions initially
            this.data.regions = await this.fetchData('region');
            this.populateSelect('region', this.data.regions);

            // Add Event Listeners
            this.selectors.region.addEventListener('change', () => this.handleRegionChange());
            this.selectors.province.addEventListener('change', () => this.handleProvinceChange());
            this.selectors.city.addEventListener('change', () => this.handleCityChange());

        } catch (error) {
            console.error('Failed to initialize PH Address Selector:', error);
        }
    }

    async fetchData(type) {
        const response = await fetch(`${PH_ADDRESS_BASE_URL}/${type}.json`);
        return await response.json();
    }

    populateSelect(type, items, selectedValue = '') {
        const select = this.selectors[type];
        if (!select) return;

        select.innerHTML = `<option value="">Select ${type.charAt(0).toUpperCase() + type.slice(1)}</option>`;
        
        // Sort items by name
        items.sort((a, b) => {
            const nameA = a[`${type}_name`] || a.name || a[`brgy_name`] || "";
            const nameB = b[`${type}_name`] || b.name || b[`brgy_name`] || "";
            return String(nameA).localeCompare(String(nameB));
        });

        items.forEach(item => {
            const name = item[`${type}_name`] || item.name || item[`brgy_name`];
            const code = item[`${type}_code`] || item[`brgy_code`];
            const option = document.createElement('option');
            option.value = name; 
            option.dataset.code = code;
            option.textContent = name;
            if (name === selectedValue) option.selected = true;
            select.appendChild(option);
        });
    }

    async handleRegionChange() {
        const selectedOption = this.selectors.region.options[this.selectors.region.selectedIndex];
        const regionCode = selectedOption.dataset.code;

        // Clear children
        this.clearSelect('province');
        this.clearSelect('city');
        this.clearSelect('barangay');

        if (!regionCode) return;

        if (this.data.provinces.length === 0) {
            this.data.provinces = await this.fetchData('province');
        }

        const filtered = this.data.provinces.filter(p => p.region_code === regionCode);
        this.populateSelect('province', filtered);
    }

    async handleProvinceChange() {
        const selectedOption = this.selectors.province.options[this.selectors.province.selectedIndex];
        const provinceCode = selectedOption.dataset.code;

        this.clearSelect('city');
        this.clearSelect('barangay');

        if (!provinceCode) return;

        if (this.data.cities.length === 0) {
            this.data.cities = await this.fetchData('city');
        }

        const filtered = this.data.cities.filter(c => c.province_code === provinceCode);
        this.populateSelect('city', filtered);
    }

    async handleCityChange() {
        const selectedOption = this.selectors.city.options[this.selectors.city.selectedIndex];
        const cityCode = selectedOption.dataset.code;

        this.clearSelect('barangay');

        if (!cityCode) return;

        if (this.data.barangays.length === 0) {
            this.data.barangays = await this.fetchData('barangay');
        }

        const filtered = this.data.barangays.filter(b => b.city_code === cityCode);
        this.populateSelect('barangay', filtered);
    }

    clearSelect(type) {
        const select = this.selectors[type];
        if (select) {
            select.innerHTML = `<option value="">Select ${type.charAt(0).toUpperCase() + type.slice(1)}</option>`;
        }
    }

    /**
     * Set values manually and sequentially (handles cascading logic)
     * @param {Object} values - { region: '...', province: '...', city: '...', barangay: '...' }
     */
    async setValues(values) {
        if (!values) return;

        // 1. Set Region
        if (values.region) {
            this.selectors.region.value = values.region;
            await this.handleRegionChange();
            
            // 2. Set Province
            if (values.province) {
                this.selectors.province.value = values.province;
                await this.handleProvinceChange();
                
                // 3. Set City
                if (values.city) {
                    this.selectors.city.value = values.city;
                    await this.handleCityChange();
                    
                    // 4. Set Barangay
                    if (values.barangay) {
                        this.selectors.barangay.value = values.barangay;
                        // No further change handler needed for barangay usually, 
                        // but trigger change for any external listeners
                        this.selectors.barangay.dispatchEvent(new Event('change'));
                    }
                }
            }
        }
    }
}

// Global initialization helper
window.initPHAddress = (prefix) => new PHAddressSelector({ prefix });
