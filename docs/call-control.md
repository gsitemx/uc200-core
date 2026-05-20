# Call Control Engine

UC200 incluye una primera capa de control de llamadas para el Web Client y operaciones UCaaS modernas.

## Alcance actual

- hold / unhold
- mute local WebRTC
- blind transfer
- attended transfer preparado
- hangup
- call pickup
- call park
- DTMF
- preparacion para conference y supervision

## Arquitectura

- **UC200 Web Client** resuelve mute y hold local donde el navegador tiene el control directo del audio.
- **CallControlService** abstrae acciones de control para panel, API y apps futuras.
- **AMI** se usa para hangup, redirect/transfer y park.
- **ARI** queda preparado para una fase posterior de control avanzado.

## Endpoints

- `POST /api/v1/calls/hold`
- `POST /api/v1/calls/unhold`
- `POST /api/v1/calls/transfer`
- `POST /api/v1/calls/hangup`
- `POST /api/v1/calls/park`
- `POST /api/v1/calls/pickup`

## Permisos

- `call.hold`
- `call.transfer`
- `call.pickup`
- `call.park`
- `call.supervise`

## Notas operativas

- `hold/unhold` funcionan en modo cliente para WebRTC y quedan auditados desde UC200.
- `transfer`, `hangup` y `park` requieren canal activo cuando se operan sobre el motor de comunicaciones.
- `pickup` usa originate con codigo de pickup configurable, por defecto `*8`.
