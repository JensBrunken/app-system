# Changelog
All notable changes to this project will be documented in this file.

## 0.2.0

**Additions/Changes**
- Added app install and uninstall commands
- Moved hmac signature to header 
- Added signature to every outgoing request
- If there is an error during App installation the app will be removed, so that it can be easily reinstalled
- Added shop url to confirmation request and requests for loading iframes
- Renamed `shop` query parameter to `shop-url` in registration request
- Allowed that `setup` element in manifest file can be omitted, in that case action-buttons, modules and webhooks won't be registered, but it allows themes to work without a registration endpoint
- Made `saas_app.path` field in DB store relative file paths instead of absolute once
- Added Content-Type `application/json` to webhook requests
- Add shop id to all outgoing requests
- Added shop id as first part for the generation of the registration proof
- Made AppLifecycleEvents hookable
- Added permission validation for webhooks
- Removed `apiKey` and `secretKey` from action and webhook requests, if you need to authenticate with the shop save the secrets during registration, where you get those as part of the request to the confirmation url
- Removed `accessToken` property from AppDefinition and according DB column from the `saas_app` table

## 0.1.0

**Initial Release**
