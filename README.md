# SteVe-OCPP-HTTP-Client
Basic HTTP client for sending commands remotely to the Steve OCPP control panel

Introduction:
This client can be used to expand the management capabilities of charging points connected to the SteVe OCPP server.
Steve OCPP server has a reliable implementation of the OCPP protocol (1.2 - 1.6 S / J), a convenient web interface.
Unfortunately, at the moment SteVe does not have an API implementation for connecting external automation systems (Home Assistant, applications).
I think this will appear, as SteVe is actively developing.
Since Steve’s web interface works over HTTP, the idea came up to create an emulator that generates and sends requests to it from outside.

Why it can be applied:
- Connecting to Home Assistant (or other smart home servers) to expand station management capabilities (for example, load balancing).
- Creation of applications requiring communication with the station (via OCPP server).

How it works:
1) We call the main.php script (you can use curl, which is suitable for many situations) with the necessary parameters in the request (the implemented commands are indicated below)
2) The script is connected to the web server
3) Steve redirects the command to the charging point.

Conclusion: This script allows you to replace a person to create some automation.

The implemented commands:
1) Get the status of the connector (it will return the current state) - curl "http: //youraddress/main.php? Key = XXXXX & ChargeBoxID = 001 & cmd = getConnectorState & ConnectorID = 2"
2) DataTransfer (will return the answer) - curl "http: //youraddress/main.php? Key = XXXXX & ChargeBoxID = 001 & cmd = DataTransfer & VendorID = YourVendor & MessageID = Hello!"
3) RemoteStartTransaction (response not implemented) - curl "http: //youraddress/main.php? Key = XXXXX & ChargeBoxID = 001 & cmd = RemoteStartTransaction & ConnectorID = 2 & idTag = ABCDE"
4) RemoteStopTransaction (response not implemented) - curl "http: //youraddress/main.php? Key = XXXXX & ChargeBoxID = 001 & cmd = RemoteStopTransaction & ConnectorID = 2 & idTag = ABCDE"
5) UnlockConnector (no response) - curl "http: //youraddress/main.php? Key = XXXXX & ChargeBoxID = 328002 & cmd = UnlockConnector & ConnectorID = 2"

Important details:
1) This script works with the JSON version of OCPP. If you have SOAP, replace JSON with SOAP in your code.
2) I wrote this for the first time, suggestions for improvement are welcome.
3) You can add new teams or leave a request.

Add setup instruction
