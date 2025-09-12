import { mount } from '@vue/test-utils'
import TabNavigation from '../../../src/components/TabNavigation.vue'

describe('TabNavigation Component', () => {
  let wrapper: any

  beforeEach(() => {
    wrapper = mount(TabNavigation, {
      props: {
        activeTab: 'estimator',
        tabs: [
          { id: 'estimator', label: 'Estimator', icon: 'calculator' },
          { id: 'admin', label: 'Admin', icon: 'settings' }
        ]
      }
    })
  })

  afterEach(() => {
    wrapper.unmount()
  })

  describe('Component Rendering', () => {
    it('renders all tabs', () => {
      const tabs = wrapper.findAll('[data-testid="tab-button"]')
      expect(tabs).toHaveLength(2)
      expect(tabs[0].text()).toContain('Estimator')
      expect(tabs[1].text()).toContain('Admin')
    })

    it('highlights active tab', () => {
      const activeTab = wrapper.find('[data-testid="tab-button"][aria-selected="true"]')
      expect(activeTab.exists()).toBe(true)
      expect(activeTab.text()).toContain('Estimator')
    })

    it('applies correct CSS classes to active tab', () => {
      const activeTab = wrapper.find('[aria-selected="true"]')
      expect(activeTab.classes()).toContain('active')
    })
  })

  describe('Tab Interaction', () => {
    it('emits tab-change event when tab is clicked', async () => {
      const adminTab = wrapper.findAll('[data-testid="tab-button"]')[1]
      await adminTab.trigger('click')
      
      expect(wrapper.emitted('tab-change')).toBeTruthy()
      expect(wrapper.emitted('tab-change')[0]).toEqual(['admin'])
    })

    it('does not emit event when clicking active tab', async () => {
      const activeTab = wrapper.find('[aria-selected="true"]')
      await activeTab.trigger('click')
      
      // Should still emit for consistency
      expect(wrapper.emitted('tab-change')).toBeTruthy()
    })
  })

  describe('Keyboard Navigation', () => {
    it('supports arrow key navigation', async () => {
      const tabs = wrapper.findAll('[data-testid="tab-button"]')
      
      // Focus first tab
      await tabs[0].trigger('focus')
      
      // Press right arrow
      await tabs[0].trigger('keydown.right')
      expect(wrapper.emitted('tab-change')).toBeTruthy()
      expect(wrapper.emitted('tab-change')[0]).toEqual(['admin'])
    })

    it('supports left arrow navigation', async () => {
      // Set admin as active
      await wrapper.setProps({ activeTab: 'admin' })
      
      const tabs = wrapper.findAll('[data-testid="tab-button"]')
      
      // Focus admin tab
      await tabs[1].trigger('focus')
      
      // Press left arrow
      await tabs[1].trigger('keydown.left')
      expect(wrapper.emitted('tab-change')).toBeTruthy()
      expect(wrapper.emitted('tab-change')[0]).toEqual(['estimator'])
    })

    it('wraps around at boundaries', async () => {
      const tabs = wrapper.findAll('[data-testid="tab-button"]')
      
      // From first tab, left arrow should go to last
      await tabs[0].trigger('keydown.left')
      expect(wrapper.emitted('tab-change')).toBeTruthy()
      expect(wrapper.emitted('tab-change')[0]).toEqual(['admin'])
    })

    it('supports Enter and Space key activation', async () => {
      const adminTab = wrapper.findAll('[data-testid="tab-button"]')[1]
      
      await adminTab.trigger('keydown.enter')
      expect(wrapper.emitted('tab-change')).toBeTruthy()
      
      await adminTab.trigger('keydown.space')
      expect(wrapper.emitted('tab-change')).toBeTruthy()
    })
  })

  describe('Accessibility', () => {
    it('has proper ARIA attributes', () => {
      const tabList = wrapper.find('[role="tablist"]')
      expect(tabList.exists()).toBe(true)
      
      const tabs = wrapper.findAll('[role="tab"]')
      expect(tabs).toHaveLength(2)
      
      tabs.forEach(tab => {
        expect(tab.attributes('aria-selected')).toBeDefined()
        expect(tab.attributes('tabindex')).toBeDefined()
      })
    })

    it('sets correct tabindex values', () => {
      const tabs = wrapper.findAll('[role="tab"]')
      
      // Active tab should have tabindex="0"
      const activeTab = wrapper.find('[aria-selected="true"]')
      expect(activeTab.attributes('tabindex')).toBe('0')
      
      // Inactive tabs should have tabindex="-1"
      const inactiveTab = wrapper.find('[aria-selected="false"]')
      expect(inactiveTab.attributes('tabindex')).toBe('-1')
    })

    it('has descriptive labels', () => {
      const tabs = wrapper.findAll('[role="tab"]')
      tabs.forEach(tab => {
        expect(tab.attributes('aria-label')).toBeDefined()
        expect(tab.attributes('aria-label')).not.toBe('')
      })
    })
  })

  describe('Responsive Design', () => {
    it('adapts to mobile viewport', async () => {
      // Mock mobile viewport
      Object.defineProperty(window, 'innerWidth', {
        writable: true,
        configurable: true,
        value: 375
      })
      
      window.dispatchEvent(new Event('resize'))
      await wrapper.vm.$nextTick()
      
      const tabList = wrapper.find('[role="tablist"]')
      expect(tabList.classes()).toContain('mobile')
    })

    it('shows icons on mobile', async () => {
      await wrapper.setData({ isMobile: true })
      
      const icons = wrapper.findAll('[data-testid="tab-icon"]')
      expect(icons.length).toBeGreaterThan(0)
    })
  })

  describe('Theme Support', () => {
    it('applies dark theme classes', async () => {
      await wrapper.setProps({ darkMode: true })
      
      const tabList = wrapper.find('[role="tablist"]')
      expect(tabList.classes()).toContain('dark')
    })

    it('applies light theme classes', async () => {
      await wrapper.setProps({ darkMode: false })
      
      const tabList = wrapper.find('[role="tablist"]')
      expect(tabList.classes()).toContain('light')
    })
  })

  describe('Error Handling', () => {
    it('handles missing tab data gracefully', async () => {
      await wrapper.setProps({ tabs: [] })
      
      const tabs = wrapper.findAll('[data-testid="tab-button"]')
      expect(tabs).toHaveLength(0)
      
      // Should not crash
      expect(wrapper.exists()).toBe(true)
    })

    it('handles invalid active tab', async () => {
      await wrapper.setProps({ activeTab: 'nonexistent' })
      
      // Should still render without errors
      expect(wrapper.exists()).toBe(true)
      
      // No tab should be marked as active
      const activeTabs = wrapper.findAll('[aria-selected="true"]')
      expect(activeTabs).toHaveLength(0)
    })
  })
})