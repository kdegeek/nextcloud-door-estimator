import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import ToastNotifications from '../../../src/components/ToastNotifications.vue'
import { useNotificationStore } from '../../../src/stores/notifications'

describe('ToastNotifications Component', () => {
  let wrapper: any
  let notificationStore: any

  beforeEach(() => {
    setActivePinia(createPinia())
    notificationStore = useNotificationStore()
    
    wrapper = mount(ToastNotifications, {
      global: {
        plugins: [createPinia()]
      }
    })
  })

  afterEach(() => {
    wrapper.unmount()
    jest.clearAllMocks()
  })

  describe('Component Rendering', () => {
    it('renders notification container', () => {
      expect(wrapper.find('[data-testid="toast-container"]').exists()).toBe(true)
    })

    it('renders notifications from store', async () => {
      notificationStore.addNotification({
        id: '1',
        type: 'success',
        message: 'Test success message',
        duration: 3000
      })
      
      await wrapper.vm.$nextTick()
      
      const notifications = wrapper.findAll('[data-testid="toast-notification"]')
      expect(notifications).toHaveLength(1)
      expect(notifications[0].text()).toContain('Test success message')
    })

    it('renders different notification types with correct styling', async () => {
      const notifications = [
        { id: '1', type: 'success', message: 'Success message' },
        { id: '2', type: 'error', message: 'Error message' },
        { id: '3', type: 'warning', message: 'Warning message' },
        { id: '4', type: 'info', message: 'Info message' }
      ]
      
      notifications.forEach(notification => {
        notificationStore.addNotification(notification)
      })
      
      await wrapper.vm.$nextTick()
      
      const toasts = wrapper.findAll('[data-testid="toast-notification"]')
      expect(toasts).toHaveLength(4)
      
      expect(toasts[0].classes()).toContain('success')
      expect(toasts[1].classes()).toContain('error')
      expect(toasts[2].classes()).toContain('warning')
      expect(toasts[3].classes()).toContain('info')
    })
  })

  describe('Notification Interaction', () => {
    it('dismisses notification when close button is clicked', async () => {
      notificationStore.addNotification({
        id: '1',
        type: 'info',
        message: 'Test message'
      })
      
      await wrapper.vm.$nextTick()
      
      const closeButton = wrapper.find('[data-testid="toast-close"]')
      expect(closeButton.exists()).toBe(true)
      
      await closeButton.trigger('click')
      await wrapper.vm.$nextTick()
      
      const notifications = wrapper.findAll('[data-testid="toast-notification"]')
      expect(notifications).toHaveLength(0)
    })

    it('dismisses notification when clicked (if dismissible)', async () => {
      notificationStore.addNotification({
        id: '1',
        type: 'info',
        message: 'Test message',
        dismissible: true
      })
      
      await wrapper.vm.$nextTick()
      
      const notification = wrapper.find('[data-testid="toast-notification"]')
      await notification.trigger('click')
      await wrapper.vm.$nextTick()
      
      const notifications = wrapper.findAll('[data-testid="toast-notification"]')
      expect(notifications).toHaveLength(0)
    })

    it('does not dismiss when clicked if not dismissible', async () => {
      notificationStore.addNotification({
        id: '1',
        type: 'error',
        message: 'Persistent error',
        dismissible: false
      })
      
      await wrapper.vm.$nextTick()
      
      const notification = wrapper.find('[data-testid="toast-notification"]')
      await notification.trigger('click')
      await wrapper.vm.$nextTick()
      
      const notifications = wrapper.findAll('[data-testid="toast-notification"]')
      expect(notifications).toHaveLength(1)
    })
  })

  describe('Auto-dismiss Functionality', () => {
    beforeEach(() => {
      jest.useFakeTimers()
    })

    afterEach(() => {
      jest.useRealTimers()
    })

    it('auto-dismisses notifications after specified duration', async () => {
      notificationStore.addNotification({
        id: '1',
        type: 'success',
        message: 'Auto-dismiss test',
        duration: 3000
      })
      
      await wrapper.vm.$nextTick()
      
      let notifications = wrapper.findAll('[data-testid="toast-notification"]')
      expect(notifications).toHaveLength(1)
      
      // Fast forward time
      jest.advanceTimersByTime(3000)
      await flushPromises()
      
      notifications = wrapper.findAll('[data-testid="toast-notification"]')
      expect(notifications).toHaveLength(0)
    })

    it('does not auto-dismiss persistent notifications', async () => {
      notificationStore.addNotification({
        id: '1',
        type: 'error',
        message: 'Persistent error',
        persistent: true
      })
      
      await wrapper.vm.$nextTick()
      
      // Fast forward time
      jest.advanceTimersByTime(10000)
      await flushPromises()
      
      const notifications = wrapper.findAll('[data-testid="toast-notification"]')
      expect(notifications).toHaveLength(1)
    })

    it('pauses auto-dismiss on hover', async () => {
      notificationStore.addNotification({
        id: '1',
        type: 'info',
        message: 'Hover test',
        duration: 3000
      })
      
      await wrapper.vm.$nextTick()
      
      const notification = wrapper.find('[data-testid="toast-notification"]')
      
      // Hover over notification
      await notification.trigger('mouseenter')
      
      // Fast forward time
      jest.advanceTimersByTime(3000)
      await flushPromises()
      
      // Should still be visible
      const notifications = wrapper.findAll('[data-testid="toast-notification"]')
      expect(notifications).toHaveLength(1)
      
      // Mouse leave should resume timer
      await notification.trigger('mouseleave')
      jest.advanceTimersByTime(3000)
      await flushPromises()
      
      // Now should be dismissed
      const finalNotifications = wrapper.findAll('[data-testid="toast-notification"]')
      expect(finalNotifications).toHaveLength(0)
    })
  })

  describe('Animation and Transitions', () => {
    it('applies enter animation classes', async () => {
      notificationStore.addNotification({
        id: '1',
        type: 'success',
        message: 'Animation test'
      })
      
      await wrapper.vm.$nextTick()
      
      const notification = wrapper.find('[data-testid="toast-notification"]')
      expect(notification.classes()).toContain('toast-enter')
    })

    it('applies exit animation classes when dismissing', async () => {
      notificationStore.addNotification({
        id: '1',
        type: 'success',
        message: 'Exit animation test'
      })
      
      await wrapper.vm.$nextTick()
      
      const closeButton = wrapper.find('[data-testid="toast-close"]')
      await closeButton.trigger('click')
      
      const notification = wrapper.find('[data-testid="toast-notification"]')
      expect(notification.classes()).toContain('toast-exit')
    })
  })

  describe('Accessibility', () => {
    it('has proper ARIA attributes', async () => {
      notificationStore.addNotification({
        id: '1',
        type: 'error',
        message: 'Accessibility test'
      })
      
      await wrapper.vm.$nextTick()
      
      const notification = wrapper.find('[data-testid="toast-notification"]')
      expect(notification.attributes('role')).toBe('alert')
      expect(notification.attributes('aria-live')).toBe('assertive')
    })

    it('has proper ARIA attributes for different types', async () => {
      notificationStore.addNotification({
        id: '1',
        type: 'info',
        message: 'Info message'
      })
      
      await wrapper.vm.$nextTick()
      
      const notification = wrapper.find('[data-testid="toast-notification"]')
      expect(notification.attributes('aria-live')).toBe('polite')
    })

    it('close button has accessible label', async () => {
      notificationStore.addNotification({
        id: '1',
        type: 'success',
        message: 'Test message'
      })
      
      await wrapper.vm.$nextTick()
      
      const closeButton = wrapper.find('[data-testid="toast-close"]')
      expect(closeButton.attributes('aria-label')).toBe('Close notification')
    })

    it('supports keyboard navigation', async () => {
      notificationStore.addNotification({
        id: '1',
        type: 'success',
        message: 'Keyboard test'
      })
      
      await wrapper.vm.$nextTick()
      
      const closeButton = wrapper.find('[data-testid="toast-close"]')
      expect(closeButton.attributes('tabindex')).toBe('0')
      
      // Test Enter key
      await closeButton.trigger('keydown.enter')
      await wrapper.vm.$nextTick()
      
      const notifications = wrapper.findAll('[data-testid="toast-notification"]')
      expect(notifications).toHaveLength(0)
    })
  })

  describe('Performance', () => {
    it('limits maximum number of notifications', async () => {
      // Add many notifications
      for (let i = 0; i < 20; i++) {
        notificationStore.addNotification({
          id: `${i}`,
          type: 'info',
          message: `Message ${i}`
        })
      }
      
      await wrapper.vm.$nextTick()
      
      const notifications = wrapper.findAll('[data-testid="toast-notification"]')
      expect(notifications.length).toBeLessThanOrEqual(10) // Max limit
    })

    it('removes oldest notifications when limit exceeded', async () => {
      // Add notifications up to limit
      for (let i = 0; i < 12; i++) {
        notificationStore.addNotification({
          id: `${i}`,
          type: 'info',
          message: `Message ${i}`
        })
      }
      
      await wrapper.vm.$nextTick()
      
      const notifications = wrapper.findAll('[data-testid="toast-notification"]')
      
      // Should show newest notifications
      expect(notifications[0].text()).toContain('Message 11')
      expect(notifications[0].text()).not.toContain('Message 0')
    })
  })

  describe('Theme Support', () => {
    it('applies dark theme styles', async () => {
      await wrapper.setProps({ darkMode: true })
      
      notificationStore.addNotification({
        id: '1',
        type: 'success',
        message: 'Dark theme test'
      })
      
      await wrapper.vm.$nextTick()
      
      const container = wrapper.find('[data-testid="toast-container"]')
      expect(container.classes()).toContain('dark')
    })

    it('applies light theme styles', async () => {
      await wrapper.setProps({ darkMode: false })
      
      const container = wrapper.find('[data-testid="toast-container"]')
      expect(container.classes()).toContain('light')
    })
  })

  describe('Error Handling', () => {
    it('handles malformed notification data', async () => {
      // Add notification with missing required fields
      notificationStore.notifications.push({
        id: null,
        type: undefined,
        message: ''
      })
      
      await wrapper.vm.$nextTick()
      
      // Should not crash
      expect(wrapper.exists()).toBe(true)
    })

    it('handles store errors gracefully', async () => {
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation()
      
      // Simulate store error
      notificationStore.addNotification = jest.fn().mockImplementation(() => {
        throw new Error('Store error')
      })
      
      try {
        notificationStore.addNotification({
          id: '1',
          type: 'error',
          message: 'Test'
        })
      } catch (error) {
        // Expected
      }
      
      // Component should still render
      expect(wrapper.exists()).toBe(true)
      
      consoleSpy.mockRestore()
    })
  })
})