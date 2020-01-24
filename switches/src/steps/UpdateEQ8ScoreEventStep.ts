/* eslint-disable @typescript-eslint/explicit-function-return-type */
import * as operatorModule from '@ns8/ns8-switchboard-operator';
import { EventSwitch } from 'ns8-switchboard-interfaces';
import { SwitchContext } from 'ns8-switchboard-interfaces';
import { ScoreHelper } from '../lib/ScoreHelper';

export class UpdateEQ8ScoreEventStep implements EventSwitch {
  async handle(switchContext: SwitchContext): Promise<any> {
    const converter = new ScoreHelper(switchContext);
    await converter.processScore();
    const result = await converter.getMagentoOrder();
    const protectData = switchContext.data;
    return { result, protectData } as any;
  }
}

export const UpdateEQ8ScoreEvent: (event: any) => Promise<any> = ((): any =>
  new operatorModule.EventOperator([new UpdateEQ8ScoreEventStep()]).handle)();
