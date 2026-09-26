# Términos Comerciales y Modelo de Doble Licenciamiento (Dual Licensing)
## jg_dashboard (Control Plane & Governance Panel)

**Repositorio oficial:** [https://gitlab.com/javiergalvez-ia/jg_dashboard](https://gitlab.com/javiergalvez-ia/jg_dashboard)  
**Titular de los derechos de autor:** Copyright (C) 2026 Javier Gálvez.

---

## 1. Resumen Ejecutivo

El panel de control y gobernanza **`jg_dashboard`** (Laravel 11 / Filament v3 / Livewire) se distribuye bajo un esquema formal de **Doble Licenciamiento (Dual Licensing)**:

1. **Licencia de Código Abierto (AGPLv3):** Destinada a la comunidad de software libre, investigación y proyectos comunitarios dispuestos a compartir íntegramente sus modificaciones y código bajo la misma licencia recíproca ([LICENSE](LICENSE)).
2. **Licencia Comercial Enterprise (1 € / mes por agente):** Diseñada para organizaciones, operadores de servicios gestionados, integradores y plataformas SaaS que requieren operar, adaptar, personalizar o distribuir el panel **sin la obligación de revelar su código fuente** ni sus personalizaciones privadas.

---

## 2. La Licencia de Código Abierto: GNU AGPLv3

Por defecto, el código fuente de `jg_dashboard` se encuentra bajo los términos de la **GNU Affero General Public License, Version 3.0 (AGPL-3.0)** tal como la publica la Free Software Foundation ([LICENSE](LICENSE)).

### La Cláusula de Interacción por Red (Sección 13 de AGPLv3)
La licencia AGPLv3 incluye una cláusula vinculante de copyleft de red (*Remote Network Interaction*):

> *Si modificas el Programa, tu versión modificada debe ofrecer de manera prominente a todos los usuarios que interactúen con él de forma remota a través de una red informática (...) una oportunidad para recibir el Código Fuente Correspondiente de tu versión.*

#### Implicaciones directas para organizaciones:
- Al tratarse de una aplicación web (Dashboard / Control Plane), cualquier usuario que acceda a la interfaz a través de la red (intranet corporativa, VPN o Internet) **adquiere el derecho legal de solicitar y recibir el código fuente completo** de dicha instalación, incluidas las modificaciones, adaptaciones de marca y módulos internos.
- Si integras adaptadores propietarios, integraciones de facturación o esquemas de autenticación personalizados en el Dashboard, la naturaleza viral de AGPLv3 puede exigir su liberación pública.

---

## 3. Licencia Comercial Enterprise

Para empresas y proveedores de servicios que necesitan utilizar `jg_dashboard` como parte de su infraestructura corporativa o catálogo comercial sin liberar código fuente, ofrecemos la **Licencia Comercial Enterprise directa del autor original**.

### 3.1 Esquema de Tarifas
- **Tarifa Única Universal:** **1 € / mes** (+ IVA aplicable) **por cada agente gestionado**, sin importe mínimo ni límite superior.
- **Sin rappel y sin límite:** La tarificación es estrictamente proporcional y lineal a razón de 1 €/mes por cada agente activo gestionado (ej. 1 agente = 1 €/mes, 10 agentes = 10 €/mes, 500 agentes = 500 €/mes), sin descuentos por tramos, sin rappel y sin empaquetado en bloques mínimos.
- **Periodicidad:** Facturación mensual o anual recurrente.
- **Casos Singulares y Acuerdos Especiales:** Si tu organización requiere un acuerdo corporativo muy particular (e.g. acuerdos OEM a medida, integración en appliances cerrados o condiciones específicas de contratación pública), puedes contactar directamente a través de **[https://javiergalvez.com](https://javiergalvez.com)**.

### 3.2 Derechos y Ventajas Otorgados
Al adquirir la Licencia Comercial Enterprise, tu organización obtiene:

1. **Exención Total de AGPLv3:** Exención formal y vinculante de los requisitos de la Sección 13 de AGPLv3. Operas el panel en red sin ninguna obligación de publicar tu código fuente.
2. **Marca Blanca y Personalización Completa:** Derecho a eliminar los logotipos y avisos de atribución pública, adaptando la interfaz gráfica a la identidad visual de tu compañía o clientes.
3. **Módulos y Adaptadores Propietarios:** Capacidad de desarrollar conectores cerrados (CRMs internos, SSO corporativo, pasarelas de pago) sin riesgo de contaminación legal.
4. **SaaS y Multi-Tenant Comercial:** Autorización para comercializar el acceso al panel a terceros como parte de una oferta SaaS o servicio gestionado (MSP).
5. **Garantía Legal de Titularidad:** Certificado de licencia comercial expedido por el titular de los derechos, protegiendo a tu organización frente a contingencias de auditoría de software libre.
6. **Soporte Técnico Especializado:** Acceso a parches de estabilidad y canal prioritario de resolución de incidencias.

---

## 4. Matriz Comparativa: AGPLv3 vs. Comercial Enterprise

| Característica / Obligación | Licencia Comunitaria (AGPLv3) | Licencia Comercial Enterprise |
| :--- | :---: | :---: |
| **Coste de Licencia** | Gratuito (0 €) | **1 € / mes por agente** |
| **Escalado de tarifa** | N/A | **Lineal (1 €/agente, sin rappel y sin límite)** |
| **Acceso al código fuente** | Sí | Sí |
| **Uso en red sin compartir código (SaaS / Web)** | **Prohibido (Sec. 13 obliga a liberar)** | **PERMITIDO (Exento)** |
| **Marca blanca (White-labeling)** | No permitido | **Permitido** |
| **Integraciones y adaptadores cerrados** | No (Contaminación copyleft) | **SÍ (100% Propietarios)** |
| **Comercialización como producto SaaS** | Requiere liberar todo el código | **Permitido bajo código cerrado** |
| **Soporte y canal directo con ingeniería** | Comunidad / Best effort | **Soporte Prioritario** |

---

## 5. Contacto y Contratación

Para solicitar el contrato de **Licencia Comercial Enterprise**, emitir facturación corporativa o plantear esquemas de gran volumen:

- **Sitio Web Oficial:** **[https://javiergalvez.com](https://javiergalvez.com)**

