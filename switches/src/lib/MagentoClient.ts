import { Customer as MagentoCustomer } from '@ns8/magento2-rest-client';
import { error } from '.';
import { Order as MagentoOrder } from '@ns8/magento2-rest-client';
import { RestApiError } from '@ns8/magento2-rest-client';
import { RestClient } from '@ns8/magento2-rest-client';
import { ServiceIntegration } from 'ns8-protect-models';
import { SwitchContext } from 'ns8-switchboard-interfaces';
import { Transaction as MagentoTransaction } from '@ns8/magento2-rest-client';

/**
 * A wrapper around the Magento2 REST Client for convience and error handling.
 * TODO: as the number of these convenience methods continues to grow, it would be worth factoring out some of the boilerplate into a private method to improve readability/maintainability.
 */
export class MagentoClient {

  private SwitchContext: SwitchContext;
  public client: RestClient;
  constructor(switchContext: SwitchContext) {
    try {
      this.SwitchContext = switchContext;
      let siTemp = this.SwitchContext.merchant.serviceIntegrations.find((integration) => {
        return integration.type === 'MAGENTO';
      });
      if (!siTemp) throw new Error('No Magento Service Integration defined on this merchant');
      const si: ServiceIntegration = siTemp;

      //Constructing the REST client has no side effects. OAuth does not take place until an API call is made.
      this.client = new RestClient({
        url: `${this.SwitchContext.merchant.storefrontUrl}/index.php/rest`,
        consumerKey: si.identityToken,
        consumerSecret: si.identitySecret,
        accessToken: si.token,
        accessTokenSecret: si.secret
      })
    } catch (e) {
      error('Failed to construct RestClient', e);
    }
  }

  /**
   * Mechanism for synchronous wait.
   * Usage: `await this.sleep(5000)`
   */
  private sleep = async (milliseconds: number) => {
    return new Promise(resolve => setTimeout(resolve, milliseconds));
  }

  /**
   * Handles specific conditions in API errors.
   * If a 404, will execute a simple retry loop.
   * Returns `false` if the API error is unhandled; otherwise returns the API response.
   */
  private handleApiError = async (error, method, params, attempts: number = 0, maxRetry: number = 5, waitMs: number = 2000): Promise<any> => {
    if (error.statusCode === 404 && attempts < maxRetry) {
      attempts += 1;
      await this.sleep(waitMs);
      const args = [...params, attempts, maxRetry, waitMs];
      return await method(...args);
    } else {
      return false;
    }
  }

  /**
   * Convenience method to get a Magento Order by OrderId from the Magento API.
   */
  public getOrder = async (orderId: number, attempts: number = 0, maxRetry: number = 5, waitMs: number = 2000): Promise<MagentoOrder | null> => {
    try {
      return await this.client.orders.get(orderId);
    } catch (e) {
      if(false === await this.handleApiError(e, this.getOrder, [orderId], attempts, maxRetry, waitMs)) {
        error(`Failed to get Order Id:${orderId} from Magento`, e);
      }
    }
    return null;
  }

  /**
   * Attempt to cancel an order. If successful, return true.
   */
  public cancelOrder = async (orderId: number): Promise<boolean> => {
    let ret = false;
    try {
      //The response is not typed; but we expect `cancel == true`
      const cancel = await this.client.orders.cancel(orderId);
      ret = true;
    } catch (e) {
        error(`Failed to cancel Order Id:${orderId} in Magento API`, e);
    }
    return ret;
  }

  /**
   * Attempt to place an order on hold. If successful, return true.
   */
  public holdOrder = async (orderId: number): Promise<boolean> => {
    let ret = false;
    try {
      //The response is not typed; but we expect `hold == true`
      const hold = await this.client.orders.hold(orderId);
      ret = true;
    } catch (e) {
      error(`Failed to hold Order Id:${orderId} in Magento API`, e);
    }
    return ret;
  }

  /**
   * Attempt to unhold an order (presumably already on hold). If successful, return true.
   */
  public unholdOrder = async (orderId: number): Promise<boolean> => {
    let ret = false;
    try {
      //The response is not typed; but we expect `hold == true`
      const hold = await this.client.orders.unhold(orderId);
      ret = true;
    } catch (e) {
      error(`Failed to hold Order Id:${orderId} in Magento API`, e);
    }
    return ret;
  }

  /**
   * Get a Magento Customer by Id
   */
  public getCustomer = async (customerId: number, attempts: number = 0, maxRetry: number = 5, waitMs: number = 2000): Promise<MagentoCustomer | null> => {
    try {
      return await this.client.customers.get(customerId);
    } catch (e) {
      if (false === await this.handleApiError(e, this.getCustomer, [customerId], attempts, maxRetry, waitMs)) {
        error(`Failed to get Customer Id:${customerId} from Magento`, e);
      }
    }
    return null;
  }

  /**
   * Get a Magento Transaction by Id
   */
  public getTransaction = async (transactionId: string, attempts: number = 0, maxRetry: number = 5, waitMs: number = 2000): Promise<MagentoTransaction | null> => {
    try {
      return await this.client.transactions.getByTransactionId(transactionId) || null;
    } catch (e) {
      if (false === await this.handleApiError(e, this.getTransaction, [transactionId], attempts, maxRetry, waitMs)) {
        error(`Failed to get Transaction Id:${transactionId} from Magento`, e);
      }
    }
    return null;
  }
}
