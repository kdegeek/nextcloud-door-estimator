import { mount } from '@vue/test-utils'
import EstimatorView from '../EstimatorView.vue'

describe('EstimatorView.vue', () => {
  it('renders without crashing', () => {
    const wrapper = mount(EstimatorView, {
      props: {
        quoteData: {},
        pricingData: {},
        darkMode: false,
        updateQuoteItem: () => {},
        calculateSectionTotal: () => 0,
        inputError: () => '',
        markups: {}
      }
    })
    expect(wrapper.exists()).toBe(true)
  })
})