import AppApiService from 'connect/core/service/api/app-api.service';
import apiResponses, { createId } from '__fixtures__/app-system/action-buttons.fixtures';

let httpClient = null;

describe('app-api.service', () => {
    beforeEach(() => {
        if (httpClient) {
            httpClient.reset();
        }
    });

    test('constructor', () => {
        const httpClient = Shopware.Application.getContainer('init').httpClient;
        const loginService = Shopware.Service('loginService');

        const apiService = new AppApiService(
            httpClient,
            loginService
        );

        expect(AppApiService.name).toBe('AppApiService');
        expect(apiService.name).toBe('AppApiService');
    });

    test('fetch available actions', (done) => {
        httpClient = Shopware.Application.getContainer('init').httpClient;
        const appApiService = new AppApiService(httpClient, Shopware.Service('loginService'));

        appApiService.getActionButtonsPerView('product', 'list')
            .then((actions) => {
                expect(Array.isArray(actions)).toBe(true);
                expect(actions.length).toBe(1);
                expect(actions).toEqual(apiResponses.actionButtons.data.actions);
                
                done();
            });

        httpClient.mockResponse(apiResponses.actionButtons);
        expect(httpClient.get).toBeCalledWith(
            'app-system/action-button/product/list',
            {
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    Authorization: 'Bearer false',
                },
            }
        );
    });

    test('fetch undefined action', (done) => {
        httpClient = Shopware.Application.getContainer('init').httpClient;
        const appApiService = new AppApiService(httpClient, Shopware.Service('loginService'));
        
        appApiService.getActionButtonsPerView()
            .then((actions) => {
                expect(Array.isArray(actions)).toBe(true);
                expect(actions.length).toBe(0); 
 
                done();
            });

        httpClient.mockResponse(apiResponses.emptyActionButtonList);
        expect(httpClient.get).toBeCalledWith(
            'app-system/action-button/undefined/undefined',
            {
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    Authorization: 'Bearer false',
                },
            }
        );
    });

    test('does not return top level array', (done) => {
        httpClient = Shopware.Application.getContainer('init').httpClient;
        const appApiService = new AppApiService(httpClient, Shopware.Service('loginService'));
        
        appApiService.getActionButtonsPerView()
            .then((actions) => {
                expect(Array.isArray(actions)).toBe(true);
                expect(actions.length).toBe(0); 
 
                done();
            });

        httpClient.mockResponse(apiResponses.malformedList);
        expect(httpClient.get).toBeCalledWith(
            'app-system/action-button/undefined/undefined',
            {
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    Authorization: 'Bearer false',
                },
            }
        );
    });

    test('run action has no response', (done) => {
        httpClient = Shopware.Application.getContainer('init').httpClient;
        const appApiService = new AppApiService(httpClient, Shopware.Service('loginService'));
        const actionId = createId();

        appApiService.runAction(actionId)
            .then((response) => {
                expect(response).toEqual([]);
                done();
            });

        httpClient.mockResponse(apiResponses.emptyResponse);

        expect(httpClient.post).toBeCalledWith(
            `app-system/action-button/run/${actionId}`,
            {},
            {
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    Authorization: 'Bearer false',
                },
            }
        );
    });
});
