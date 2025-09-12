import { EstimatorApi, type ApiClientOptions } from './EstimatorApi'

export function createEstimatorApi(opts?: ApiClientOptions) {
  return new EstimatorApi(opts)
}

export type { EstimatorApi }