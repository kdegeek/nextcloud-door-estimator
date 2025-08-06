import { mount } from '@vue/test-utils'
import AdminView from '../AdminView.vue'

describe('AdminView.vue', () => {
  it('renders without crashing', () => {
    const wrapper = mount(AdminView)
    expect(wrapper.exists()).toBe(true)
  })
})