# Implementación y contratos

`AgentHttpClient` genera HMAC y headers de control, con timeout/retry. El agente no los verifica (AGENT-001), así que la firma es actualmente telemetría sin efecto de seguridad. También configura un secreto por defecto (`secret_internal_control_plane`) que no debe ser una credencial de despliegue.

El registro de bootstrap usa hash y `lockForUpdate` al consumo, punto positivo. Las aprobaciones hacen comprobación `isPending()` y después llamada remota sin una transición atómica de reserva: dos solicitudes concurrentes pueden observar PENDING y enviar dos resumes antes de que una actualice la fila.

La vista de ejecuciones mezcla snapshot remoto y `ApprovalRequest` local. No existe tabla de execution records en Dashboard; por diseño la UI depende de que el agente devuelva datos consistentes.
