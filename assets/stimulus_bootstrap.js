import { startStimulusApp } from '@symfony/stimulus-bridge';

const controllerContext = import.meta.webpackContext('./controllers', {
	recursive: true,
	regExp: /\.(j|t)sx?$/,
});

export const app = startStimulusApp(controllerContext);
