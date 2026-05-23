/**
 * ph-address-selector.js
 * 
 * PURPOSE: This script handles the "Cascading Dropdowns" for Philippine addresses.
 * "Cascading" means that when you pick a Region, the Province list automatically updates, 
 * and when you pick a Province, the City list updates, etc.
 * 
 * External Data source: https://github.com/isaacdarcilla/philippine-addresses
 */

const PH_ADDRESS_BASE_URL = 'https://isaacdarcilla.github.io/philippine-addresses';

class PHAddressSelector {
    constructor(config) {
        // 'prefix' allows us to have MULTIPLE address selectors on one page 
        // (e.g., 'home-region' and 'office-region') without them clashing.
        this.prefix = config.prefix || ''; 
        
        // Find the HTML <select> elements on the page
        this.selectors = {
            region: document.getElementById(`${this.prefix}region`),
            province: document.getElementById(`${this.prefix}province`),
            city: document.getElementById(`${this.prefix}city`),
            barangay: document.getElementById(`${this.prefix}barangay`)
        };

        // Local storage for the JSON data we fetch from the internet
        this.data = {
            regions: [],
            provinces: [],
            cities: [],
            barangays: []
        };

        this.init();
    }

    /**
     * Start the engine: Load regions and watch for user clicks
     */
    async init() {
        try {
            // STEP 1: Load Regions immediately when the page loads
            this.data.regions = await this.fetchData('region');
            this.populateSelect('region', this.data.regions);

            // STEP 2: Listen for changes. When a user picks a region, trigger the province update.
            this.selectors.region.addEventListener('change', () => this.handleRegionChange());
            this.selectors.province.addEventListener('change', () => this.handleProvinceChange());
            this.selectors.city.addEventListener('change', () => this.handleCityChange());

        } catch (error) {
            console.error('Failed to initialize PH Address Selector:', error);
        }
    }

    /**
     * Download the JSON file for a specific level (region, province, city, or barangay)
     */
    async fetchData(type) {
        const response = await fetch(`${PH_ADDRESS_BASE_URL}/${type}.json`);
        return await response.json();
    }

    /**
     * Take a list of items and turn them into <option> tags inside a <select> dropdown
     */
    populateSelect(type, items, selectedValue = '') {
        const select = this.selectors[type];
        if (!select) return;

        // Clear the dropdown and add a placeholder
        select.innerHTML = `<option value="">Select ${type.charAt(0).toUpperCase() + type.slice(1)}</option>`;
        
        // Sort items alphabetically so they are easy to find
        items.sort((a, b) => {
            const nameA = a[`${type}_name`] || a.name || a[`brgy_name`] || "";
            const nameB = b[`${type}_name`] || b.name || b[`brgy_name`] || "";
            return String(nameA).localeCompare(String(nameB));
        });

        // Add each item to the dropdown
        items.forEach(item => {
            const name = item[`${type}_name`] || item.name || item[`brgy_name`];
            const code = item[`${type}_code`] || item[`brgy_code`];
            const option = document.createElement('option');
            option.value = name; 
            option.dataset.code = code; // Store the unique PSGC code in a hidden attribute
            option.textContent = name;
            if (name === selectedValue) option.selected = true;
            select.appendChild(option);
        });
    }

    /**
     * Triggered when REGION changes: Filters Provinces
     */
    async handleRegionChange() {
        const selectedOption = this.selectors.region.options[this.selectors.region.selectedIndex];
        const regionCode = selectedOption.dataset.code;

        // Reset all lower dropdowns
        this.clearSelect('province');
        this.clearSelect('city');
        this.clearSelect('barangay');

        if (!regionCode) return;

        // If we haven't downloaded the province list yet, do it now
        if (this.data.provinces.length === 0) {
            this.data.provinces = await this.fetchData('province');
        }

        // Show only provinces that belong to the selected region
        const filtered = this.data.provinces.filter(p => p.region_code === regionCode);
        this.populateSelect('province', filtered);
    }

    /**
     * Triggered when PROVINCE changes: Filters Cities
     */
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

    /**
     * Triggered when CITY changes: Filters Barangays
     */
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

    /**
     * Helper to empty a dropdown
     */
    clearSelect(type) {
        const select = this.selectors[type];
        if (select) {
            select.innerHTML = `<option value="">Select ${type.charAt(0).toUpperCase() + type.slice(1)}</option>`;
        }
    }

    /**
     * Advanced: Used when editing a profile to pre-fill the selections
     */
    async setValues(values) {
        if (!values) return;

        if (values.region) {
            this.selectors.region.value = values.region;
            await this.handleRegionChange();
            
            if (values.province) {
                this.selectors.province.value = values.province;
                await this.handleProvinceChange();
                
                if (values.city) {
                    this.selectors.city.value = values.city;
                    await this.handleCityChange();
                    
                    if (values.barangay) {
                        this.selectors.barangay.value = values.barangay;
                        this.selectors.barangay.dispatchEvent(new Event('change'));
                    }
                }
            }
        }
    }
}

// Attach a global function to the window so we can easily start a selector from any HTML file
window.initPHAddress = (prefix) => new PHAddressSelector({ prefix });
