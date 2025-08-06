import { mount } from '@vue/test-utils'
import DoorEstimatorApp from '../DoorEstimatorApp.vue'

describe('DoorEstimatorApp.vue', () => {
  it('renders without crashing', () => {
    const wrapper = mount(DoorEstimatorApp)
    expect(wrapper.exists()).toBe(true)
  })
})