<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Acceso institucional',
        description: 'Plataforma institucional de inteligencia artificial',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Acceso institucional" />

    <div class="ai-login">

        <!-- =====================================================
             FONDO
        ====================================================== -->

        <div class="global-grid"></div>

        <div class="ambient ambient-left"></div>
        <div class="ambient ambient-right"></div>

        <!-- =====================================================
             PARTÍCULAS
        ====================================================== -->

        <div class="particles" aria-hidden="true">
            <span class="particle p1"></span>
            <span class="particle p2"></span>
            <span class="particle p3"></span>
            <span class="particle p4"></span>
            <span class="particle p5"></span>
            <span class="particle p6"></span>
            <span class="particle p7"></span>
            <span class="particle p8"></span>
            <span class="particle p9"></span>
            <span class="particle p10"></span>
            <span class="particle p11"></span>
            <span class="particle p12"></span>
            <span class="particle p13"></span>
            <span class="particle p14"></span>
            <span class="particle p15"></span>
            <span class="particle p16"></span>
            <span class="particle p17"></span>
            <span class="particle p18"></span>
            <span class="particle p19"></span>
            <span class="particle p20"></span>
        </div>

        <!-- =====================================================
             PANTALLA
        ====================================================== -->

        <main class="screen">

            <!-- =================================================
                 PANEL INSTITUCIONAL
            ================================================== -->

            <section class="institution-side">

                <div class="institution-content">

                    <!-- LOGO -->

                    <div class="logo-area">
                        <img
                            src="/images/logo_fgjtam.png"
                            alt="Logo institucional"
                            class="institution-logo"
                        />
                    </div>

                    <div class="small-line"></div>

                    <div class="institution-label">
                        PLATAFORMA INSTITUCIONAL
                    </div>

                    <h1 class="main-title">
                        Ministerio Público
                        <span>IA</span>
                    </h1>

                    <p class="main-description">
                        Tecnología inteligente para transformar,
                        analizar y gestionar información institucional
                        de manera eficiente y segura.
                    </p>

                    <!-- ESTADO DEL SISTEMA -->

                    <div class="system-status">

                        <span class="status-indicator"></span>

                        <span>
                            SISTEMA OPERATIVO
                        </span>

                        <i></i>

                        <span class="status-text">
                            IA ACTIVA
                        </span>

                    </div>

                </div>


                <!-- =================================================
                     RED NEURONAL
                ================================================== -->

                <div
                    class="neural-network"
                    aria-hidden="true"
                >

                    <div class="neural-core">

                        <div class="orbit orbit-1"></div>
                        <div class="orbit orbit-2"></div>
                        <div class="orbit orbit-3"></div>

                        <div class="core">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                            >

                                <circle
                                    cx="12"
                                    cy="12"
                                    r="3"
                                    stroke="currentColor"
                                    stroke-width="1.3"
                                />

                                <path
                                    d="M12 2V7"
                                    stroke="currentColor"
                                    stroke-width="1.3"
                                    stroke-linecap="round"
                                />

                                <path
                                    d="M12 17V22"
                                    stroke="currentColor"
                                    stroke-width="1.3"
                                    stroke-linecap="round"
                                />

                                <path
                                    d="M2 12H7"
                                    stroke="currentColor"
                                    stroke-width="1.3"
                                    stroke-linecap="round"
                                />

                                <path
                                    d="M17 12H22"
                                    stroke="currentColor"
                                    stroke-width="1.3"
                                    stroke-linecap="round"
                                />

                            </svg>

                        </div>

                    </div>

                    <span class="neural-node n1"></span>
                    <span class="neural-node n2"></span>
                    <span class="neural-node n3"></span>
                    <span class="neural-node n4"></span>
                    <span class="neural-node n5"></span>
                    <span class="neural-node n6"></span>

                    <span class="neural-line nl1"></span>
                    <span class="neural-line nl2"></span>
                    <span class="neural-line nl3"></span>
                    <span class="neural-line nl4"></span>
                    <span class="neural-line nl5"></span>
                    <span class="neural-line nl6"></span>

                </div>


                <!-- FOOTER -->

                <div class="institution-footer">

                    <span>
                        PLATAFORMA IA
                    </span>

                    <span class="footer-separator">
                        /
                    </span>

                    <span>
                        ACCESO INSTITUCIONAL
                    </span>

                </div>

            </section>


            <!-- =================================================
                 LOGIN
            ================================================== -->

            <section class="login-side">

                <div class="login-content">

                    <!-- HEADER -->

                    <div class="login-header">

                        <div class="access-label">

                            <span></span>

                            ACCESO SEGURO

                        </div>

                        <h2>
                            Bienvenido
                        </h2>

                        <p>
                            Ingresa tus credenciales institucionales
                            para continuar.
                        </p>

                    </div>


                    <!-- STATUS -->

                    <div
                        v-if="status"
                        class="status-message"
                    >

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            aria-hidden="true"
                        >

                            <circle
                                cx="12"
                                cy="12"
                                r="9"
                                stroke="currentColor"
                                stroke-width="1.5"
                            />

                            <path
                                d="M8.5 12L10.8 14.3L15.5 9.7"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />

                        </svg>

                        <span>
                            {{ status }}
                        </span>

                    </div>


                    <!-- =================================================
                         FORMULARIO
                    ================================================== -->

                    <Form
                        v-bind="store.form()"
                        :reset-on-success="['password']"
                        v-slot="{ errors, processing }"
                        class="login-form"
                    >

                        <!-- =================================================
                             CORREO
                        ================================================== -->

                        <div class="field">

                            <Label
                                for="email"
                                class="field-label"
                            >
                                Correo electrónico
                            </Label>

                            <div
                                class="input-container"
                                :class="{
                                    'input-error': errors.email
                                }"
                            >

                                <div class="field-icon">

                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        aria-hidden="true"
                                    >

                                        <path
                                            d="M4 5H20C21.1 5 22 5.9 22 7V17C22 18.1 21.1 19 20 19H4C2.9 19 2 18.1 2 17V7C2 5.9 2.9 5 4 5Z"
                                            stroke="currentColor"
                                            stroke-width="1.5"
                                            stroke-linejoin="round"
                                        />

                                        <path
                                            d="M3 7L12 13L21 7"
                                            stroke="currentColor"
                                            stroke-width="1.5"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />

                                    </svg>

                                </div>

                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autofocus
                                    :tabindex="1"
                                    autocomplete="email"
                                    placeholder="correo@institucion.gob.mx"
                                    class="field-input"
                                />

                            </div>

                            <InputError
                                :message="errors.email"
                                class="error-message"
                            />

                        </div>


                        <!-- =================================================
                             CONTRASEÑA
                        ================================================== -->

                        <div class="field">

                            <div class="password-label">

                                <Label
                                    for="password"
                                    class="field-label"
                                >
                                    Contraseña
                                </Label>

                                <TextLink
                                    v-if="canResetPassword"
                                    :href="request()"
                                    :tabindex="5"
                                    class="forgot-password"
                                >
                                    ¿Olvidaste tu contraseña?
                                </TextLink>

                            </div>


                            <div
                                class="input-container"
                                :class="{
                                    'input-error': errors.password
                                }"
                            >

                                <!-- ICONO -->

                                <div class="field-icon">

                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        aria-hidden="true"
                                    >

                                        <rect
                                            x="4"
                                            y="10"
                                            width="16"
                                            height="11"
                                            rx="2"
                                            stroke="currentColor"
                                            stroke-width="1.5"
                                        />

                                        <path
                                            d="M8 10V7C8 4.79 9.79 3 12 3C14.21 3 16 4.79 16 7V10"
                                            stroke="currentColor"
                                            stroke-width="1.5"
                                            stroke-linecap="round"
                                        />

                                        <circle
                                            cx="12"
                                            cy="15.5"
                                            r="1"
                                            fill="currentColor"
                                        />

                                    </svg>

                                </div>


                                <!-- PASSWORD -->

                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    :tabindex="2"
                                    autocomplete="current-password"
                                    placeholder="Ingresa tu contraseña"
                                    class="field-input"
                                />

                            </div>

                            <InputError
                                :message="errors.password"
                                class="error-message"
                            />

                        </div>


                        <!-- =================================================
                             RECORDAR SESIÓN
                        ================================================== -->

                        <div class="remember">

                            <Label
                                for="remember"
                                class="remember-label"
                            >

                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    :tabindex="3"
                                    class="remember-checkbox"
                                />

                                <span>
                                    Mantener sesión iniciada
                                </span>

                            </Label>

                        </div>


                        <!-- =================================================
                             BOTÓN
                        ================================================== -->

                        <Button
                            type="submit"
                            :tabindex="4"
                            :disabled="processing"
                            data-test="login-button"
                            class="login-button"
                        >

                            <template v-if="processing">

                                <Spinner class="button-spinner" />

                                <span>
                                    Verificando credenciales...
                                </span>

                            </template>

                            <template v-else>

                                <span>
                                    Ingresar al sistema
                                </span>

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    aria-hidden="true"
                                >

                                    <path
                                        d="M5 12H19"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                        stroke-linecap="round"
                                    />

                                    <path
                                        d="M13 6L19 12L13 18"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />

                                </svg>

                            </template>

                        </Button>

                    </Form>


                    <!-- =================================================
                         REGISTRO
                    ================================================== -->

                    <div class="register">

                        <span>
                            ¿No tienes una cuenta?
                        </span>

                        <TextLink
                            :href="register()"
                            :tabindex="6"
                            class="register-link"
                        >
                            Crear cuenta
                        </TextLink>

                    </div>


                    <!-- =================================================
                         SEGURIDAD
                    ================================================== -->

                    <div class="security">

                        <div class="security-icon">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                aria-hidden="true"
                            >

                                <path
                                    d="M12 3L5 6V11.5C5 16.1 7.8 19.8 12 21C16.2 19.8 19 16.1 19 11.5V6L12 3Z"
                                    stroke="currentColor"
                                    stroke-width="1.4"
                                    stroke-linejoin="round"
                                />

                                <path
                                    d="M9 12L11 14L15 10"
                                    stroke="currentColor"
                                    stroke-width="1.4"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />

                            </svg>

                        </div>

                        <div>

                            <strong>
                                Conexión segura
                            </strong>

                            <span>
                                Acceso protegido para usuarios institucionales.
                            </span>

                        </div>

                    </div>


                    <!-- FOOTER -->

                    <div class="login-footer">
                        Sistema institucional de inteligencia artificial
                    </div>

                </div>

            </section>

        </main>

    </div>
</template>


<style scoped>

/* =========================================================
   VARIABLES
========================================================= */

.ai-login {

    --dark: #575756;
    --gray: #878787;
    --light: #dadada;

    position: relative;

    width: 100%;
    min-height: 100svh;

    overflow: hidden;

    display: flex;

    background:
        linear-gradient(
            105deg,
            #575756 0%,
            #575756 52%,
            #f6f6f5 52.1%,
            #f6f6f5 100%
        );

    color: var(--dark);

    isolation: isolate;
}


/* =========================================================
   GRID
========================================================= */

.global-grid {

    position: absolute;

    inset: 0;

    pointer-events: none;

    background-image:
        linear-gradient(
            rgba(255,255,255,.025) 1px,
            transparent 1px
        ),
        linear-gradient(
            90deg,
            rgba(255,255,255,.025) 1px,
            transparent 1px
        );

    background-size: 45px 45px;

    opacity: .65;

    z-index: -5;
}


/* =========================================================
   LUCES AMBIENTALES
========================================================= */

.ambient {

    position: absolute;

    width: 600px;
    height: 600px;

    border-radius: 50%;

    filter: blur(100px);

    pointer-events: none;

    z-index: -4;
}

.ambient-left {

    left: -300px;
    top: -250px;

    background:
        rgba(218,218,218,.08);

    animation:
        ambientLeft
        14s
        ease-in-out
        infinite
        alternate;
}

.ambient-right {

    right: -250px;
    bottom: -300px;

    background:
        rgba(87,87,86,.07);

    animation:
        ambientRight
        17s
        ease-in-out
        infinite
        alternate;
}


/* =========================================================
   PARTÍCULAS
========================================================= */

.particles {

    position: absolute;

    inset: 0;

    pointer-events: none;

    z-index: -2;
}

.particle {

    position: absolute;

    width: 3px;
    height: 3px;

    border-radius: 50%;

    background: #dadada;

    opacity: .3;

    animation:
        particle
        var(--speed)
        ease-in-out
        infinite
        alternate;
}

.p1 {
    left: 5%;
    top: 10%;
    --speed: 8s;
}

.p2 {
    left: 12%;
    top: 35%;
    --speed: 10s;
}

.p3 {
    left: 18%;
    top: 80%;
    --speed: 7s;
}

.p4 {
    left: 26%;
    top: 22%;
    --speed: 12s;
}

.p5 {
    left: 32%;
    top: 65%;
    --speed: 9s;
}

.p6 {
    left: 40%;
    top: 14%;
    --speed: 11s;
}

.p7 {
    left: 46%;
    top: 87%;
    --speed: 8s;
}

.p8 {
    left: 53%;
    top: 30%;
    --speed: 13s;
}

.p9 {
    left: 61%;
    top: 74%;
    --speed: 9s;
}

.p10 {
    left: 68%;
    top: 12%;
    --speed: 11s;
}

.p11 {
    left: 74%;
    top: 43%;
    --speed: 8s;
}

.p12 {
    left: 80%;
    top: 83%;
    --speed: 10s;
}

.p13 {
    left: 86%;
    top: 20%;
    --speed: 7s;
}

.p14 {
    left: 91%;
    top: 60%;
    --speed: 12s;
}

.p15 {
    left: 96%;
    top: 34%;
    --speed: 9s;
}

.p16 {
    left: 22%;
    top: 48%;
    --speed: 13s;
}

.p17 {
    left: 58%;
    top: 53%;
    --speed: 10s;
}

.p18 {
    left: 78%;
    top: 68%;
    --speed: 8s;
}

.p19 {
    left: 88%;
    top: 88%;
    --speed: 11s;
}

.p20 {
    left: 35%;
    top: 38%;
    --speed: 9s;
}


/* =========================================================
   SCREEN
========================================================= */

.screen {

    position: relative;

    width: 100%;
    min-height: 100svh;

    display: grid;

    grid-template-columns: 58% 42%;
}


/* =========================================================
   PANEL INSTITUCIONAL
========================================================= */

.institution-side {

    position: relative;

    min-height: 100svh;

    display: flex;

    flex-direction: column;

    justify-content: space-between;

    padding:
        clamp(40px, 7vw, 100px);

    overflow: hidden;

    color: white;
}


/* =========================================================
   CONTENIDO INSTITUCIONAL
========================================================= */

.institution-content {

    position: relative;

    z-index: 5;

    max-width: 620px;

    animation:
        institutionAppear
        .9s
        cubic-bezier(.22,1,.36,1);
}


/* =========================================================
   LOGO
========================================================= */

.logo-area {

    margin-bottom: 45px;
}

.institution-logo {

    width: auto;

    max-width: 300px;

    max-height: 100px;

    object-fit: contain;

    filter:
        brightness(0)
        invert(1);

    opacity: .96;
}


/* =========================================================
   TITULO INSTITUCIONAL
========================================================= */

.small-line {

    width: 42px;
    height: 2px;

    margin-bottom: 22px;

    background:
        rgba(255,255,255,.55);
}

.institution-label {

    margin-bottom: 14px;

    color:
        rgba(255,255,255,.50);

    font-size: 9px;

    font-weight: 700;

    letter-spacing: .22em;
}

.main-title {

    margin: 0;

    font-size:
        clamp(
            55px,
            6vw,
            92px
        );

    line-height: .92;

    font-weight: 300;

    letter-spacing: -.065em;
}

.main-title span {

    display: block;

    font-weight: 700;

    color: white;
}

.main-description {

    max-width: 510px;

    margin-top: 30px;

    color:
        rgba(255,255,255,.58);

    font-size: 14px;

    line-height: 1.8;
}


/* =========================================================
   ESTADO
========================================================= */

.system-status {

    display: flex;

    align-items: center;

    gap: 9px;

    margin-top: 38px;

    color:
        rgba(255,255,255,.50);

    font-size: 8px;

    font-weight: 700;

    letter-spacing: .16em;
}

.status-indicator {

    width: 6px;
    height: 6px;

    border-radius: 50%;

    background: #dadada;

    box-shadow:
        0 0 0 4px
        rgba(218,218,218,.08);

    animation:
        pulse
        2s
        ease-in-out
        infinite;
}

.system-status i {

    width: 35px;
    height: 1px;

    background:
        rgba(255,255,255,.15);
}

.status-text {

    color:
        rgba(255,255,255,.75);
}


/* =========================================================
   RED NEURONAL
========================================================= */

.neural-network {

    position: absolute;

    width: 560px;
    height: 560px;

    right: -90px;
    bottom: -80px;

    opacity: .65;

    z-index: 1;
}

.neural-core {

    position: absolute;

    left: 50%;
    top: 50%;

    width: 120px;
    height: 120px;

    transform:
        translate(-50%, -50%);
}

.core {

    position: absolute;

    left: 50%;
    top: 50%;

    width: 55px;
    height: 55px;

    display: flex;

    align-items: center;
    justify-content: center;

    transform:
        translate(-50%, -50%);

    border:
        1px solid
        rgba(255,255,255,.35);

    border-radius: 50%;

    background:
        rgba(255,255,255,.04);

    color:
        rgba(255,255,255,.75);

    box-shadow:
        0 0 40px
        rgba(255,255,255,.10);
}

.core svg {

    width: 26px;
    height: 26px;
}

.orbit {

    position: absolute;

    inset: 0;

    border:
        1px solid
        rgba(255,255,255,.18);

    border-radius: 50%;
}

.orbit-1 {

    animation:
        rotate
        12s
        linear
        infinite;
}

.orbit-2 {

    inset: -35px;

    border-style: dashed;

    opacity: .4;

    animation:
        rotateReverse
        18s
        linear
        infinite;
}

.orbit-3 {

    inset: -70px;

    opacity: .18;

    animation:
        orbitPulse
        5s
        ease-in-out
        infinite;
}


/* =========================================================
   NODOS
========================================================= */

.neural-node {

    position: absolute;

    width: 7px;
    height: 7px;

    border-radius: 50%;

    background: #dadada;

    box-shadow:
        0 0 15px
        rgba(218,218,218,.4);

    animation:
        nodePulse
        3s
        ease-in-out
        infinite;
}

.n1 {
    left: 13%;
    top: 20%;
}

.n2 {
    right: 14%;
    top: 17%;
    animation-delay: .5s;
}

.n3 {
    left: 5%;
    bottom: 27%;
    animation-delay: 1s;
}

.n4 {
    right: 7%;
    bottom: 23%;
    animation-delay: 1.5s;
}

.n5 {
    left: 48%;
    top: 3%;
    animation-delay: 2s;
}

.n6 {
    left: 45%;
    bottom: 1%;
    animation-delay: 2.5s;
}


/* =========================================================
   LÍNEAS
========================================================= */

.neural-line {

    position: absolute;

    height: 1px;

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(255,255,255,.30),
            transparent
        );

    transform-origin: left center;
}

.nl1 {
    width: 220px;
    left: 15%;
    top: 23%;
    transform: rotate(25deg);
}

.nl2 {
    width: 210px;
    right: 15%;
    top: 21%;
    transform: rotate(155deg);
}

.nl3 {
    width: 240px;
    left: 8%;
    bottom: 29%;
    transform: rotate(-25deg);
}

.nl4 {
    width: 220px;
    right: 10%;
    bottom: 25%;
    transform: rotate(154deg);
}

.nl5 {
    width: 190px;
    left: 45%;
    top: 5%;
    transform: rotate(90deg);
}

.nl6 {
    width: 190px;
    left: 43%;
    bottom: 3%;
    transform: rotate(-90deg);
}


/* =========================================================
   FOOTER INSTITUCIONAL
========================================================= */

.institution-footer {

    position: relative;

    z-index: 5;

    display: flex;

    gap: 10px;

    color:
        rgba(255,255,255,.28);

    font-size: 7px;

    font-weight: 700;

    letter-spacing: .18em;
}

.footer-separator {

    color:
        rgba(255,255,255,.12);
}


/* =========================================================
   LOGIN SIDE
========================================================= */

.login-side {

    position: relative;

    min-height: 100svh;

    display: flex;

    align-items: center;

    justify-content: center;

    padding:
        60px
        clamp(40px, 7vw, 120px);

    background:
        rgba(246,246,245,.96);
}


/* =========================================================
   LOGIN CONTENT
========================================================= */

.login-content {

    width: 100%;

    max-width: 420px;

    animation:
        loginAppear
        .8s
        .15s
        both
        cubic-bezier(.22,1,.36,1);
}


/* =========================================================
   HEADER LOGIN
========================================================= */

.login-header {

    margin-bottom: 34px;
}

.access-label {

    display: flex;

    align-items: center;

    gap: 8px;

    margin-bottom: 14px;

    color: #999999;

    font-size: 8px;

    font-weight: 700;

    letter-spacing: .18em;
}

.access-label span {

    width: 5px;
    height: 5px;

    border-radius: 50%;

    background: #575756;

    animation:
        pulse
        2s
        infinite;
}

.login-header h2 {

    margin: 0;

    color: #454544;

    font-size:
        clamp(
            34px,
            3vw,
            44px
        );

    line-height: 1;

    font-weight: 700;

    letter-spacing: -.05em;
}

.login-header p {

    max-width: 360px;

    margin-top: 12px;

    color: #9b9b9b;

    font-size: 12px;

    line-height: 1.7;
}


/* =========================================================
   STATUS
========================================================= */

.status-message {

    display: flex;

    align-items: center;

    gap: 9px;

    margin-bottom: 20px;

    padding: 11px 13px;

    border:
        1px solid
        rgba(87,87,86,.12);

    border-radius: 9px;

    background:
        rgba(87,87,86,.04);

    color: #575756;

    font-size: 10px;
}


/* =========================================================
   FORM
========================================================= */

.login-form {

    display: flex;

    flex-direction: column;

    gap: 20px;
}

.field {

    display: flex;

    flex-direction: column;

    gap: 7px;
}

.field-label {

    color: #626261 !important;

    font-size: 11px !important;

    font-weight: 650 !important;
}

.password-label {

    display: flex;

    align-items: center;

    justify-content: space-between;
}

.forgot-password {

    color: #929292 !important;

    font-size: 10px !important;

    text-decoration: none !important;

    transition:
        color .2s ease;
}

.forgot-password:hover {

    color: #575756 !important;
}


/* =========================================================
   INPUT CONTENEDOR
========================================================= */

.input-container {

    display: flex;

    align-items: center;

    width: 100%;
    height: 52px;

    overflow: hidden;

    border:
        1px solid
        #dededd;

    border-radius: 9px;

    background: #ffffff;

    transition:
        border-color .25s ease,
        box-shadow .25s ease,
        transform .25s ease;
}

.input-container:hover {

    border-color: #c6c6c5;
}

.input-container:focus-within {

    border-color: #878787;

    box-shadow:
        0 0 0 3px
        rgba(87,87,86,.06);

    transform:
        translateY(-1px);
}

.input-container.input-error {

    border-color: #b45353;
}


/* =========================================================
   ICONO
========================================================= */

.field-icon {

    width: 48px;

    height: 100%;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #999999;

    transition:
        color .2s ease;
}

.field-icon svg {

    width: 18px;
    height: 18px;
}

.input-container:focus-within .field-icon {

    color: #575756;
}


/* =========================================================
   INPUT CORREO
========================================================= */

.input-container > :deep(.field-input) {

    flex: 1;

    min-width: 0;
}

.field-input {

    height: 100% !important;

    padding:
        0 15px 0 0 !important;

    border: none !important;

    border-radius: 0 !important;

    background: transparent !important;

    box-shadow: none !important;

    outline: none !important;

    color: #575756 !important;

    font-size: 12px !important;
}

.field-input::placeholder {

    color: #b2b2b2 !important;
}


/* =========================================================
   PASSWORD
========================================================= */

.input-container > :deep(.password-input-wrapper) {

    flex: 1;

    min-width: 0;

    height: 100%;
}


/* =========================================================
   ERRORES
========================================================= */

.error-message {

    margin-top: 1px;

    font-size: 10px !important;
}


/* =========================================================
   RECORDAR
========================================================= */

.remember {

    margin-top: -2px;
}

.remember-label {

    display: flex;

    align-items: center;

    gap: 8px;

    color: #929292 !important;

    font-size: 10px !important;

    cursor: pointer;

    user-select: none;
}

.remember-checkbox {

    width: 15px !important;
    height: 15px !important;
}


/* =========================================================
   BOTÓN
========================================================= */

.login-button {

    position: relative;

    width: 100%;
    height: 53px;

    overflow: hidden;

    display: flex;

    align-items: center;
    justify-content: center;

    gap: 10px;

    border: none !important;

    border-radius: 9px !important;

    background:
        linear-gradient(
            135deg,
            #575756,
            #696968
        ) !important;

    color: white !important;

    font-size: 11px !important;

    font-weight: 700 !important;

    letter-spacing: .02em;

    box-shadow:
        0 12px 28px
        rgba(87,87,86,.18);

    transition:
        transform .25s ease,
        box-shadow .25s ease;
}

.login-button::before {

    content: '';

    position: absolute;

    top: 0;
    left: -120%;

    width: 70%;
    height: 100%;

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(255,255,255,.15),
            transparent
        );

    transform:
        skewX(-20deg);

    transition:
        left .8s ease;
}

.login-button:hover::before {

    left: 140%;
}

.login-button:hover:not(:disabled) {

    transform:
        translateY(-2px);

    box-shadow:
        0 16px 35px
        rgba(87,87,86,.24);
}

.login-button:active:not(:disabled) {

    transform:
        translateY(0);
}

.login-button svg {

    width: 17px;
    height: 17px;

    transition:
        transform .25s ease;
}

.login-button:hover svg {

    transform:
        translateX(4px);
}

.login-button:disabled {

    opacity: .7;

    cursor: wait;
}


/* =========================================================
   REGISTRO
========================================================= */

.register {

    display: flex;

    justify-content: center;

    gap: 5px;

    margin-top: 23px;

    color: #9d9d9d;

    font-size: 10px;
}

.register-link {

    color: #575756 !important;

    font-weight: 700;

    text-decoration: none !important;
}

.register-link:hover {

    opacity: .65;

    text-decoration: underline !important;
}


/* =========================================================
   SEGURIDAD
========================================================= */

.security {

    display: flex;

    align-items: center;

    gap: 11px;

    margin-top: 30px;

    padding-top: 20px;

    border-top:
        1px solid
        #e8e8e7;

    color: #999999;
}

.security-icon {

    width: 31px;
    height: 31px;

    flex-shrink: 0;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 8px;

    background: #ededec;

    color: #777777;
}

.security-icon svg {

    width: 16px;
    height: 16px;
}

.security > div:last-child {

    display: flex;

    flex-direction: column;

    gap: 2px;
}

.security strong {

    color: #777777;

    font-size: 9px;
}

.security span {

    color: #aaaaaa;

    font-size: 8px;
}


/* =========================================================
   FOOTER
========================================================= */

.login-footer {

    margin-top: 26px;

    text-align: center;

    color: #b4b4b4;

    font-size: 7px;

    letter-spacing: .13em;

    text-transform: uppercase;
}


/* =========================================================
   ANIMACIONES
========================================================= */

@keyframes loginAppear {

    from {
        opacity: 0;
        transform: translateX(30px);
    }

    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes institutionAppear {

    from {
        opacity: 0;
        transform: translateX(-30px);
    }

    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes particle {

    from {

        transform:
            translate3d(0,0,0)
            scale(.7);

        opacity: .12;
    }

    to {

        transform:
            translate3d(20px,-25px,0)
            scale(1.2);

        opacity: .4;
    }
}

@keyframes pulse {

    0%,
    100% {

        opacity: .4;

        transform:
            scale(.8);
    }

    50% {

        opacity: 1;

        transform:
            scale(1.15);
    }
}

@keyframes rotate {

    from {
        transform: rotate(0);
    }

    to {
        transform: rotate(360deg);
    }
}

@keyframes rotateReverse {

    from {
        transform: rotate(360deg);
    }

    to {
        transform: rotate(0);
    }
}

@keyframes orbitPulse {

    0%,
    100% {

        transform:
            scale(.95);

        opacity: .12;
    }

    50% {

        transform:
            scale(1.08);

        opacity: .3;
    }
}

@keyframes nodePulse {

    0%,
    100% {

        opacity: .3;

        transform:
            scale(.7);
    }

    50% {

        opacity: 1;

        transform:
            scale(1.35);
    }
}

@keyframes ambientLeft {

    from {
        transform: translate(0,0);
    }

    to {
        transform: translate(80px,60px);
    }
}

@keyframes ambientRight {

    from {
        transform: translate(0,0);
    }

    to {
        transform: translate(-80px,-60px);
    }
}


/* =========================================================
   TABLET
========================================================= */

@media (max-width: 1000px) {

    .screen {

        grid-template-columns:
            50% 50%;
    }

    .institution-side {

        padding:
            55px;
    }

    .main-title {

        font-size:
            clamp(
                48px,
                6vw,
                70px
            );
    }

    .neural-network {

        right: -180px;
        bottom: -50px;

        transform:
            scale(.8);
    }

    .login-side {

        padding:
            45px;
    }
}


/* =========================================================
   MÓVIL
========================================================= */

@media (max-width: 720px) {

    .ai-login {

        display: block;

        min-height: 100svh;

        background:
            #f6f6f5;
    }

    .screen {

        display: block;

        min-height: 100svh;
    }

    .institution-side {

        min-height:
            34svh;

        padding:
            32px 28px;

        background:
            linear-gradient(
                145deg,
                #575756,
                #70706f
            );
    }

    .logo-area {

        margin-bottom: 20px;
    }

    .institution-logo {

        max-width: 190px;

        max-height: 60px;
    }

    .small-line {

        margin-bottom: 14px;
    }

    .institution-label {

        font-size: 7px;
    }

    .main-title {

        font-size: 38px;
    }

    .main-description,
    .system-status,
    .neural-network {

        display: none;
    }

    .institution-footer {

        display: none;
    }

    .login-side {

        min-height:
            66svh;

        padding:
            42px 28px;

        background:
            #f6f6f5;
    }

    .login-content {

        max-width:
            480px;
    }
}


/* =========================================================
   MÓVIL PEQUEÑO
========================================================= */

@media (max-width: 420px) {

    .institution-side {

        min-height:
            30svh;

        padding:
            27px 22px;
    }

    .institution-logo {

        max-width:
            165px;
    }

    .main-title {

        font-size:
            34px;
    }

    .login-side {

        min-height:
            70svh;

        padding:
            34px 21px;
    }

    .login-header {

        margin-bottom:
            27px;
    }

    .login-header h2 {

        font-size:
            32px;
    }
}


/* =========================================================
   ACCESIBILIDAD
========================================================= */

@media (prefers-reduced-motion: reduce) {

    *,
    *::before,
    *::after {

        animation-duration:
            .01ms !important;

        animation-iteration-count:
            1 !important;

        transition-duration:
            .01ms !important;
    }
}

</style>
